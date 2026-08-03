<?php
namespace App\Services\Viva;
use App\Enums\VivaProcessingStatus;
use App\Models\VivaImportBatch;
use App\Models\VivaProcessingState;
use Illuminate\Support\Facades\DB;
use Throwable;
final class VivaMappingApprovalService
{
 public function approve(int $batchId,int $actorId):VivaImportBatch{
  $batch=VivaImportBatch::query()->where('import_type','mapping')->findOrFail($batchId);$batch->update(['status'=>'approving','processed_rows'=>0,'approved_rows'=>0,'progress_percent'=>0,'failure_message'=>null,'finished_at'=>null]);
  try{$eligible=(int)DB::connection('exam')->table('viva_mapping_import_staging')->where('batch_id',$batchId)->where('validation_status','valid')->count();$done=$ins=$upd=0;
   DB::connection('exam')->table('viva_mapping_import_staging')->where('batch_id',$batchId)->where('validation_status','valid')->orderBy('id')->chunkById(2000,function($rows)use($batch,$batchId,$eligible,&$done,&$ins,&$upd){$ts=now()->format('Y-m-d H:i:s');foreach($rows as$r){$old=DB::connection('exam')->table('viva_candidate_mappings')->where('registration_id',$r->registration_id)->first();$payload=['registration_id'=>$r->registration_id,'written_result_id'=>$r->written_result_id,'user_id'=>$r->user_id,'reg'=>$r->reg,'code'=>$r->code,'source_batch_id'=>$batchId,'updated_at'=>$ts];if($old){DB::connection('exam')->table('viva_candidate_mappings')->where('id',$old->id)->update($payload);$upd++;}else{$payload['created_at']=$ts;DB::connection('exam')->table('viva_candidate_mappings')->insert($payload);$ins++;}}$done+=count($rows);$batch->update(['processed_rows'=>$done,'approved_rows'=>$done,'inserted_rows'=>$ins,'updated_rows'=>$upd,'progress_percent'=>$eligible?min(100,round($done/$eligible*100,4)):100]);});
   $batch->update(['status'=>'approved','approved_rows'=>$done,'processed_rows'=>$done,'inserted_rows'=>$ins,'updated_rows'=>$upd,'progress_percent'=>100,'approved_by'=>$actorId,'approved_at'=>now(),'finished_at'=>now()]);
   VivaProcessingState::query()->updateOrCreate(['id'=>1],['status'=>VivaProcessingStatus::MappingImported->value,'latest_mapping_batch_id'=>$batchId,'is_stale'=>false,'stale_reason'=>null]);return $batch->refresh();
  }catch(Throwable$e){$batch->update(['status'=>'failed','failure_message'=>mb_substr($e->getMessage(),0,65000),'finished_at'=>now()]);throw$e;}
 }
}
