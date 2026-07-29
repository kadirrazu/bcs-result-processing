<?php
namespace App\Services\Registrations;
use App\Models\RegistrationImportBatch;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;
use Throwable;
/** Stream a spreadsheet in bounded windows and bulk-upsert registrations. */
final class RegistrationImportService
{
 public function __construct(private RegistrationMasterMap $maps,private RegistrationRowNormalizer $normalizer,private RegistrationRowValidator $validator){}
 public function import(UploadedFile $file,int $userId): RegistrationImportBatch
 {
  $batch=RegistrationImportBatch::query()->create(['original_name'=>$file->getClientOriginalName(),'status'=>'processing','started_at'=>now(),'created_by'=>$userId]);
  try{
   $reader=IOFactory::createReaderForFile($file->getRealPath());$reader->setReadDataOnly(true);
   $info=$reader->listWorksheetInfo($file->getRealPath());$highest=(int)($info[0]['totalRows']??0);if($highest<1)throw new RuntimeException('Spreadsheet is empty.');
   $master=$this->maps->load();$size=max(500,(int)config('registrations.chunk_size',2000));$errors=[];$processed=0;$valid=0;
   for($start=2;$start<=$highest;$start+=$size){$end=min($highest,$start+$size-1);$reader->setReadFilter(new RegistrationChunkReadFilter($start,$end));$book=$reader->load($file->getRealPath());$rows=$book->getActiveSheet()->toArray(null,true,true,false);$headers=array_map(fn($v)=>strtolower(trim((string)$v)),array_shift($rows));if($headers!==config('registrations.headers'))throw new RuntimeException('Headers do not match the downloaded registration template.');$write=[];
    foreach($rows as $offset=>$values){$rowNo=$start+$offset;if(collect($values)->every(fn($v)=>$v===null||trim((string)$v)===''))continue;$processed++;$raw=array_combine($headers,array_slice(array_pad($values,count($headers),null),0,count($headers)));$data=$this->normalizer->normalize($raw,$batch->id);$rowErrors=$this->validator->validate($data,$master);if($rowErrors){$errors[]=['row'=>$rowNo,'reg'=>$data['reg'],'errors'=>implode(' | ',$rowErrors)];continue;}$write[]=$data;$valid++;}
    if($write)$this->write($write);$book->disconnectWorksheets();unset($book,$rows,$write);
   }
   $batch->update(['status'=>$errors?'completed_with_errors':'completed','total_rows'=>$processed,'inserted_rows'=>$valid,'failed_rows'=>count($errors),'finished_at'=>now()]);session()->put('registration_import_errors_'.$batch->id,$errors);return $batch;
  }catch(Throwable $e){$batch->update(['status'=>'failed','finished_at'=>now()]);throw $e;}
 }
 private function write(array $rows): void{DB::connection('exam')->table('registrations')->upsert($rows,['reg'],array_values(array_diff(array_keys($rows[0]),['reg','created_at'])));}
}
