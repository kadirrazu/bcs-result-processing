<?php
namespace App\Services\Viva;

use App\Jobs\ProcessVivaMappingImport;
use App\Models\VivaImportBatch;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use RuntimeException;
use Throwable;
use ZipArchive;

final class VivaMappingImportService
{
    public function enqueue(UploadedFile $file, int $userId, int $examinationId): VivaImportBatch
    {
        $ext = strtolower($file->getClientOriginalExtension());
        $stored = sprintf('viva-imports/mapping/%s-%s.%s', now()->format('YmdHis'), bin2hex(random_bytes(8)), $ext);
        $file->storeAs(dirname($stored), basename($stored), 'local');
        $batch = VivaImportBatch::query()->create([
            'examination_id'=>$examinationId,'import_type'=>'mapping','original_name'=>$file->getClientOriginalName(),
            'stored_name'=>$stored,'status'=>'queued','queued_at'=>now(),'created_by'=>$userId,
        ]);
        ProcessVivaMappingImport::dispatch($examinationId,$batch->id,$userId);
        return $batch;
    }

    public function process(int $batchId): VivaImportBatch
    {
        $batch=VivaImportBatch::query()->where('import_type','mapping')->findOrFail($batchId);
        $path=Storage::disk('local')->path($batch->stored_name);
        if(!is_file($path)) throw new RuntimeException('The uploaded Viva candidate mapping file is missing.');
        DB::connection('exam')->table('viva_mapping_import_staging')->where('batch_id',$batchId)->delete();
        $batch->update(['status'=>'staging','started_at'=>$batch->started_at??now(),'failure_message'=>null,'processed_rows'=>0,'staged_rows'=>0,'progress_percent'=>0]);
        $reader=null;
        try {
            $ext=strtolower(pathinfo($path,PATHINFO_EXTENSION));
            $reader=match($ext){'xlsx'=>new XlsxReader(),'csv'=>new CsvReader(),default=>throw new RuntimeException('Use an XLSX or CSV Viva candidate mapping file.')};
            $total=$this->quickTotalRows($path,$ext); if($total>0)$batch->update(['total_rows'=>$total]);
            $reader->open($path); $headers=null; $sourceRow=0; $staged=0; $buffer=[]; $chunk=max(500,(int)config('viva.mapping_staging_chunk_size',4000)); $ts=now()->format('Y-m-d H:i:s');
            foreach($reader->getSheetIterator() as $sheet){foreach($sheet->getRowIterator() as $row){$sourceRow++;$values=$row->toArray(); if($sourceRow===1){$headers=$this->headers($values);continue;} if($this->emptyRow($values))continue;
                $vals=array_slice(array_pad($values,3,null),0,3);$raw=array_combine($headers,$vals);
                $payload=['user'=>$this->text($raw['user']??null),'reg'=>$this->text($raw['reg']??null),'code'=>$this->text($raw['code']??null)];
                $buffer[]=['batch_id'=>$batchId,'source_row'=>$sourceRow,'raw_payload'=>json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'registration_id'=>null,'written_result_id'=>null,'user_id'=>$this->user($payload['user']),'reg'=>$this->reg($payload['reg']),'code'=>$this->code($payload['code']),'validation_status'=>'pending','validation_errors'=>null,'validation_warnings'=>null,'created_at'=>$ts,'updated_at'=>$ts];
                if(count($buffer)>=$chunk){DB::connection('exam')->table('viva_mapping_import_staging')->insert($buffer);$staged+=count($buffer);$buffer=[];$this->progress($batch,$staged,$total);}
            } break;}
            if($buffer){DB::connection('exam')->table('viva_mapping_import_staging')->insert($buffer);$staged+=count($buffer);}
            $reader->close();$reader=null;$batch->update(['status'=>'staged','total_rows'=>$staged,'processed_rows'=>$staged,'staged_rows'=>$staged,'progress_percent'=>100,'finished_at'=>now()]);return $batch->refresh();
        } catch(Throwable $e){if($reader){try{$reader->close();}catch(Throwable){}}$batch->update(['status'=>'failed','failure_message'=>mb_substr($e->getMessage(),0,65000),'finished_at'=>now()]);throw $e;}
    }
    private function headers(array $v):array{$actual=array_map(fn($x)=>strtolower(trim((string)$x)),array_slice($v,0,3));$expected=array_values((array)config('viva.mapping_headers'));if($actual!==$expected)throw new RuntimeException('Headers do not match the Viva candidate mapping template. Expected: '.implode(', ',$expected));return $expected;}
    private function emptyRow(array $v):bool{foreach(array_slice($v,0,3) as $x)if(trim((string)($x??''))!=='')return false;return true;}
    private function progress($b,$n,$t):void{$b->update(['processed_rows'=>$n,'staged_rows'=>$n,'progress_percent'=>$t>0?min(99.9,round($n/$t*100,4)):0]);}
    private function quickTotalRows(string $p,string $e):int{if($e!=='xlsx')return 0;$z=new ZipArchive();if($z->open($p)!==true)return 0;$s=$z->getStream('xl/worksheets/sheet1.xml');if(!$s){$z->close();return 0;}$x=fread($s,131072)?:'';fclose($s);$z->close();return preg_match('/<dimension[^>]+ref="(?:[A-Z]+\d+:)?[A-Z]+(\d+)"/i',$x,$m)===1?max(0,(int)$m[1]-1):0;}
    private function text($v):?string{$s=trim((string)($v??''));return $s===''?null:$s;}
    private function user($v):?string{$s=strtoupper(trim((string)($v??'')));return $s===''?null:$s;}
    private function reg($v):?string{$s=trim((string)($v??''));if(str_ends_with($s,'.0'))$s=substr($s,0,-2);return $s===''?null:$s;}
    private function code($v):?string{$s=trim((string)($v??''));if(str_ends_with($s,'.0'))$s=substr($s,0,-2);return $s===''?null:$s;}
}
