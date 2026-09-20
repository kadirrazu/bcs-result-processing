<?php

namespace App\Services\NonCadre\SeatBreakup;

use App\Services\NonCadre\NonCadreReadinessService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

final class NonCadreSeatBreakupService
{
    private const HEADERS = ['post_grade','post_serial','post_sub_serial','post_code','total_post','merit','cff','em','phc'];

    public function __construct(private readonly NonCadreReadinessService $readiness) {}

    public function templatePath(): string
    {
        $this->readiness->requireReady();
        $circular = $this->effectiveCircular();
        $posts = $this->circularPosts((int) $circular->id);
        $quota = $this->quotaConfig();

        $book = new Spreadsheet();
        $book->getProperties()
            ->setCustomProperty('non_cadre_circular_version', (int) $circular->version)
            ->setCustomProperty('non_cadre_circular_dataset_hash', (string) ($circular->dataset_hash ?? ''));
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('Seat Breakup');
        $sheet->fromArray([self::HEADERS], null, 'A1');

        $row = 2;
        foreach ($posts as $post) {
            [$mq,$cff,$em,$phc] = $this->provisionalBreakup((int) $post->post_count, $quota);
            $sheet->fromArray([[
                $post->post_grade,
                (int) $post->post_serial,
                $post->post_sub_serial,
                (string) $post->post_code,
                (int) $post->post_count,
                $mq,$cff,$em,$phc,
            ]], null, 'A'.$row);
            $sheet->setCellValueExplicit('D'.$row, (string) $post->post_code, DataType::TYPE_STRING);
            $row++;
        }
        $sheet->freezePane('A2');
        $sheet->getStyle('A1:I1')->getFont()->setBold(true);
        $sheet->getStyle('D:D')->getNumberFormat()->setFormatCode('@');
        foreach (range('A','I') as $column) $sheet->getColumnDimension($column)->setAutoSize(true);

        $path = tempnam(sys_get_temp_dir(), 'non-cadre-seat-breakup-');
        if ($path === false) throw new \RuntimeException('Unable to create Non-Cadre Seat Breakup workbook.');
        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();
        return $path;
    }

    public function import(UploadedFile $file, ?int $actorId): object
    {
        $this->readiness->requireReady();
        $circular = $this->effectiveCircular();
        $expected = $this->circularPosts((int) $circular->id)->keyBy(fn ($p) => (string) $p->post_code);
        $quota = $this->quotaConfig();

        $book = IOFactory::load($file->getRealPath());
        $raw = $book->getActiveSheet()->toArray(null, true, true, false);
        $header = array_map(static fn ($v) => strtolower(trim((string) $v)), array_shift($raw) ?? []);
        if ($header !== self::HEADERS) {
            throw ValidationException::withMessages(['file' => 'Seat Breakup header must be exactly: '.implode(', ', self::HEADERS).'.']);
        }

        $rows=[]; $seen=[]; $errors=[];
        foreach ($raw as $index => $values) {
            if (collect($values)->every(fn ($v) => $v === null || trim((string) $v) === '')) continue;
            $line=$index+2;
            $code=trim((string)($values[3] ?? ''));
            if ($code === '' || !isset($expected[$code])) { $errors[]="Row {$line}: post_code={$code} does not exist in the current finalized Non-Cadre Circular v{$circular->version}."; continue; }
            if (isset($seen[$code])) { $errors[]="Row {$line}: duplicate post_code={$code}."; continue; }
            $seen[$code]=true;
            $post=$expected[$code];

            $uploadedGrade=$this->nullableInteger($values[0] ?? null);
            $uploadedSerial=$this->nullableInteger($values[1] ?? null);
            $uploadedSub=$this->nullableInteger($values[2] ?? null);
            if ($uploadedSerial === null || $uploadedSerial !== (int)$post->post_serial || $uploadedGrade !== ($post->post_grade === null ? null : (int)$post->post_grade) || $uploadedSub !== ($post->post_sub_serial === null ? null : (int)$post->post_sub_serial)) {
                $errors[]="Row {$line}: grade/serial/sub-serial does not match the current finalized Circular for post_code={$code}."; continue;
            }

            $nums=[];
            foreach ([4,5,6,7,8] as $col) {
                $value=$values[$col] ?? null; $trim=trim((string)($value ?? ''));
                if ($trim==='' && in_array($col,[6,7,8],true)) { $nums[$col]=0; continue; }
                if ($trim==='' || !ctype_digit($trim)) { $errors[]="Row {$line}: total_post and merit must be non-negative integers; blank cff/em/phc cells are treated as 0."; continue 2; }
                $nums[$col]=(int)$trim;
            }
            [$total,$mq,$cff,$em,$phc]=[$nums[4],$nums[5],$nums[6],$nums[7],$nums[8]];
            if ($total !== (int)$post->post_count) { $errors[]="Row {$line}: total_post={$total} does not match Circular post_count={$post->post_count} for post_code={$code}."; continue; }
            if ($mq+$cff+$em+$phc !== $total) { $errors[]="Row {$line}: merit+cff+em+phc must equal total_post."; continue; }
            if ($total < $quota['minimum'] && ($mq !== $total || $cff !== 0 || $em !== 0 || $phc !== 0)) { $errors[]="Row {$line}: total_post below {$quota['minimum']} must be 100% Merit/MQ with zero CFF/EM/PHC."; continue; }
            $rows[]=['circular_post_id'=>(int)$post->id,'post_code'=>$code,'total_post'=>$total,'mq_post'=>$mq,'cff_post'=>$cff,'em_post'=>$em,'phc_post'=>$phc];
        }
        $book->disconnectWorksheets();
        if (count($seen) !== $expected->count()) $errors[]='Seat Breakup must contain every ACTIVE post from the current finalized Non-Cadre Circular exactly once.';
        if ($errors) throw ValidationException::withMessages(['file'=>array_slice($errors,0,25)]);

        $sourceHash=hash_file('sha256',$file->getRealPath()) ?: null;
        return DB::connection('exam')->transaction(function () use ($rows,$circular,$file,$sourceHash,$actorId,$quota) {
            $next=((int)DB::connection('exam')->table('non_cadre_seat_breakup_versions')->max('version'))+1;
            $id=DB::connection('exam')->table('non_cadre_seat_breakup_versions')->insertGetId([
                'version'=>$next,'circular_version_id'=>$circular->id,'status'=>'validated','source_filename'=>$file->getClientOriginalName(),'source_hash'=>$sourceHash,'created_by'=>$actorId,'created_at'=>now(),'updated_at'=>now(),
            ]);
            foreach($rows as $row) DB::connection('exam')->table('non_cadre_seat_breakup_rows')->insert($row+['seat_breakup_version_id'=>$id,'created_at'=>now(),'updated_at'=>now()]);
            $this->audit('upload_validated',$id,null,['version'=>$next,'circular_version'=>(int)$circular->version,'rows'=>count($rows),'quota_config'=>$quota],$actorId);
            return DB::connection('exam')->table('non_cadre_seat_breakup_versions')->find($id);
        });
    }

    public function finalize(int $versionId, ?int $actorId): object
    {
        $this->readiness->requireReady();
        return DB::connection('exam')->transaction(function () use ($versionId,$actorId) {
            $version=DB::connection('exam')->table('non_cadre_seat_breakup_versions')->lockForUpdate()->find($versionId);
            if(!$version) abort(404);
            if($version->status==='finalized' && !$version->is_stale) return $version;
            if($version->status!=='validated' || $version->is_stale) throw ValidationException::withMessages(['seat_breakup'=>'Only a current validated Seat Breakup version can be finalized.']);
            $circular=$this->effectiveCircular();
            if((int)$version->circular_version_id !== (int)$circular->id) throw ValidationException::withMessages(['seat_breakup'=>'The Non-Cadre Circular changed after Seat Breakup validation. Generate/upload a new Seat Breakup.']);
            $hash=$this->hashRows($versionId);
            $previous=DB::connection('exam')->table('non_cadre_seat_breakup_versions')->where('status','finalized')->where('id','<>',$versionId)->get();
            DB::connection('exam')->table('non_cadre_seat_breakup_versions')->where('status','finalized')->where('id','<>',$versionId)->update(['status'=>'outdated','is_stale'=>true,'stale_reason'=>'Superseded by Non-Cadre Seat Breakup v'.$version->version,'staled_at'=>now(),'updated_at'=>now()]);
            DB::connection('exam')->table('non_cadre_seat_breakup_versions')->where('id',$versionId)->update(['status'=>'finalized','dataset_hash'=>$hash,'is_stale'=>false,'stale_reason'=>null,'staled_at'=>null,'finalized_by'=>$actorId,'finalized_at'=>now(),'updated_at'=>now()]);
            $state=['status'=>'in_progress','seat_breakup_status'=>'finalized','is_stale'=>false,'stale_reason'=>null,'staled_at'=>null,'updated_at'=>now()];
            if($previous->isNotEmpty()){
                $reason='Non-Cadre Seat Breakup advanced to v'.$version->version.'. Re-run Non-Cadre Allocation and Reporting.';
                $state['allocation_status']='stale'; $state['reporting_status']='stale';
                DB::connection('exam')->table('non_cadre_allocation_runs')->where('is_stale',false)->update(['is_stale'=>true,'stale_reason'=>$reason,'staled_at'=>now(),'updated_at'=>now()]);
            }
            DB::connection('exam')->table('non_cadre_processing_states')->where('id',1)->update($state);
            $this->audit('version_finalized',$versionId,['status'=>$version->status],['status'=>'finalized','dataset_hash'=>$hash],$actorId);
            return DB::connection('exam')->table('non_cadre_seat_breakup_versions')->find($versionId);
        });
    }

    public function effectiveCircular(): object
    {
        $v=DB::connection('exam')->table('non_cadre_circular_versions')->where('status','finalized')->where('is_stale',false)->orderByDesc('version')->first();
        if(!$v) throw ValidationException::withMessages(['circular'=>'Finalize a current Non-Cadre Circular before NC2 Seat Breakup.']);
        return $v;
    }

    public function orderedRows(int $versionId): Collection
    {
        return DB::connection('exam')->table('non_cadre_seat_breakup_rows as s')->join('non_cadre_circular_posts as p','p.id','=','s.circular_post_id')
            ->where('s.seat_breakup_version_id',$versionId)->select('s.*','p.post_grade','p.post_serial','p.post_sub_serial','p.post_title','p.post_title_bn','p.entity','p.entity_bn')
            ->orderByRaw('p.post_grade IS NULL ASC')->orderBy('p.post_grade')->orderBy('p.post_serial')->orderByRaw('p.post_sub_serial IS NULL DESC')->orderBy('p.post_sub_serial')->get();
    }

    public function totals(int $versionId): array
    {
        $q=DB::connection('exam')->table('non_cadre_seat_breakup_rows')->where('seat_breakup_version_id',$versionId);
        return ['rows'=>(clone $q)->count(),'total'=>(int)(clone $q)->sum('total_post'),'mq'=>(int)(clone $q)->sum('mq_post'),'cff'=>(int)(clone $q)->sum('cff_post'),'em'=>(int)(clone $q)->sum('em_post'),'phc'=>(int)(clone $q)->sum('phc_post')];
    }

    private function circularPosts(int $circularId): Collection
    {
        return DB::connection('exam')->table('non_cadre_circular_posts')->where('circular_version_id',$circularId)->whereRaw('UPPER(status) = ?', ['ACTIVE'])
            ->orderByRaw('post_grade IS NULL ASC')->orderBy('post_grade')->orderBy('post_serial')->orderByRaw('post_sub_serial IS NULL DESC')->orderBy('post_sub_serial')->get();
    }

    private function quotaConfig(): array
    {
        $p=(array)config('allocation.provisional_breakup_percentages',[]);
        $out=['mq'=>(int)($p['mq']??0),'cff'=>(int)($p['cff']??0),'em'=>(int)($p['em']??0),'phc'=>(int)($p['phc']??0),'minimum'=>(int)config('allocation.quota_breakup_minimum_total_posts',10)];
        if($out['minimum']<1 || $out['mq']+$out['cff']+$out['em']+$out['phc']!==100) throw new \RuntimeException('Invalid authoritative quota Seat Breakup configuration in config/allocation.php.');
        return $out;
    }

    private function provisionalBreakup(int $total,array $q): array
    {
        if($total<$q['minimum']) return [$total,0,0,0];
        $order=['mq','cff','em','phc']; $priority=['mq'=>0,'cff'=>1,'em'=>2,'phc'=>2]; $stable=array_flip($order); $seats=[];$rem=[];
        foreach($order as $b){$n=$total*$q[$b];$seats[$b]=intdiv($n,100);$rem[$b]=$n%100;}
        $left=$total-array_sum($seats); $ranked=$order;
        usort($ranked,fn($a,$b)=>($rem[$b]<=>$rem[$a]) ?: ($priority[$a]<=>$priority[$b]) ?: ($stable[$a]<=>$stable[$b]));
        for($i=0;$i<$left;$i++)$seats[$ranked[$i]]++;
        return [$seats['mq'],$seats['cff'],$seats['em'],$seats['phc']];
    }

    private function hashRows(int $versionId): string
    {
        $payload=$this->orderedRows($versionId)->map(fn($r)=>[(string)$r->post_code,(int)$r->total_post,(int)$r->mq_post,(int)$r->cff_post,(int)$r->em_post,(int)$r->phc_post])->values()->all();
        return hash('sha256',json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
    }

    private function nullableInteger(mixed $value): ?int
    {
        $v=trim((string)($value??'')); if($v==='')return null; return ctype_digit($v)?(int)$v:null;
    }

    private function audit(string $action,int $id,?array $before,?array $after,?int $actor):void
    {
        DB::connection('exam')->table('non_cadre_processing_audits')->insert(['stage'=>'seat_breakup','action'=>$action,'entity_type'=>'seat_breakup_version','entity_id'=>$id,'before_payload'=>$before?json_encode($before):null,'after_payload'=>$after?json_encode($after):null,'actor_id'=>$actor,'created_at'=>now()]);
    }
}
