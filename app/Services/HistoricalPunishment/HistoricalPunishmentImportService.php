<?php
namespace App\Services\HistoricalPunishment;
use App\Models\HistoricalPunishment;
use App\Services\PreviousBcsRepository\PreviousBcsDateNormalizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
final class HistoricalPunishmentImportService
{
    public function import(UploadedFile $file, int $actorId): int
    {
        $sheet=IOFactory::load($file->getRealPath())->getActiveSheet(); $rows=$sheet->toArray(null,true,true,false);
        if(!$rows) return 0;
        $headers=array_map(fn($v)=>strtolower(trim((string)$v)), array_shift($rows));
        $required=['bcs','reg','name','fname','mname','b_date','dob','dist_name','ssc_roll','ssc_year','hsc_roll','hsc_year','nid_no','is_lifetime','punishment_start','punishment_end','punishment_reason'];
        foreach($required as $h) if(!in_array($h,$headers,true)) throw ValidationException::withMessages(['file'=>"Missing column: {$h}"]);
        $count=0; $date=new PreviousBcsDateNormalizer();
        DB::transaction(function()use($rows,$headers,$file,$actorId,$date,&$count){
            foreach($rows as $i=>$values){ $r=array_combine($headers,array_pad($values,count($headers),null)); if(!collect($r)->filter(fn($v)=>trim((string)$v)!=='')->count()) continue;
                $lifetime=in_array(strtoupper(trim((string)($r['is_lifetime']??''))),['1','TRUE','YES','Y'],true);
                if(!is_numeric($r['bcs']??null)) throw ValidationException::withMessages(['file'=>'Invalid BCS at Excel row '.($i+2)]);
                $bd=$date->bDate($r['b_date']??null); $od=$date->optionalDob($r['dob']??null); $bdate=$bd['date']; $dob=$od['date'];
                if(!$bdate) throw ValidationException::withMessages(['file'=>'Invalid b_date at Excel row '.($i+2)]);
                $start=$lifetime?null:$this->ymd($r['punishment_start']??null); $end=$lifetime?null:$this->ymd($r['punishment_end']??null);
                if(!$lifetime && !$end) throw ValidationException::withMessages(['file'=>'Non-lifetime punishment requires punishment_end at Excel row '.($i+2).'; punishment_start may be blank.']);
                HistoricalPunishment::query()->create(['bcs'=>(int)$r['bcs'],'reg'=>$this->t($r['reg']),'name'=>$this->t($r['name']),'fname'=>$this->t($r['fname']),'mname'=>$this->t($r['mname']),'b_date'=>$bdate,'dob'=>$dob,'dist_name'=>$this->t($r['dist_name']),'ssc_roll'=>$this->t($r['ssc_roll']),'ssc_year'=>$this->n($r['ssc_year']),'hsc_roll'=>$this->t($r['hsc_roll']),'hsc_year'=>$this->n($r['hsc_year']),'nid_no'=>$this->t($r['nid_no']),'is_lifetime'=>$lifetime,'punishment_start'=>$start,'punishment_end'=>$end,'punishment_reason'=>$this->t($r['punishment_reason']),'source_type'=>'xlsx','source_file'=>$file->getClientOriginalName(),'source_row'=>$i+2,'created_by'=>$actorId,'updated_by'=>$actorId]); $count++;
            }
        }); return $count;
    }
    private function t($v):?string{$v=trim((string)$v);return $v===''?null:$v;} private function n($v):?int{return $v===null||$v===''?null:(int)$v;}
    private function ymd($v):?string{$v=trim((string)$v);if($v==='')return null;try{return \Carbon\Carbon::parse($v)->format('Y-m-d');}catch(\Throwable){return null;}}
}
