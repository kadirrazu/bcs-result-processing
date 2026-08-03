<?php
namespace App\Services\Viva;

use App\Models\VivaImportBatch;
use App\Models\WrittenProcessingState;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final class VivaMappingValidationService
{
    public function validate(int $batchId): VivaImportBatch
    {
        $batch=VivaImportBatch::query()->where('import_type','mapping')->findOrFail($batchId);
        $state=WrittenProcessingState::query()->first();
        if(!$state?->result_finalized_at || $state->is_stale) throw new RuntimeException('The current Written result must be finalized before Viva candidate mapping can be validated.');
        $batch->update(['status'=>'validating','failure_message'=>null,'processed_rows'=>0,'valid_rows'=>0,'warning_rows'=>0,'invalid_rows'=>0,'identity_conflict_rows'=>0,'progress_percent'=>0,'finished_at'=>null]);
        try{
            $total=(int)$batch->staged_rows;$done=$valid=$invalid=$conflicts=0;$chunk=max(500,(int)config('viva.mapping_validation_chunk_size',3000));
            DB::connection('exam')->table('viva_mapping_import_staging')->where('batch_id',$batchId)->orderBy('id')->chunkById($chunk,function($rows)use($batch,$total,&$done,&$valid,&$invalid,&$conflicts){
                $regs=$rows->pluck('reg')->filter()->unique()->values()->all();$users=$rows->pluck('user_id')->filter()->unique()->values()->all();$codes=$rows->pluck('code')->filter()->unique()->values()->all();
                $registrations=DB::connection('exam')->table('registrations')->whereIn('reg',$regs)->orWhereIn('user_id',$users)->get(['id','reg','user_id'])->keyBy('reg');
                $written=DB::connection('exam')->table('written_results')->whereIn('reg',$regs)->where('status','active')->whereNotNull('written_qualified_track')->whereNotNull('finalized_at')->get(['id','registration_id','reg','user_id','written_qualified_track'])->keyBy('reg');
                $dupes=DB::connection('exam')->table('viva_mapping_import_staging')->select('code',DB::raw('COUNT(*) c'))->where('batch_id',$batch->id)->whereIn('code',$codes)->whereNotNull('code')->groupBy('code')->having('c','>',1)->pluck('c','code');
                $existing=DB::connection('exam')->table('viva_candidate_mappings')->whereIn('code',$codes)->get(['registration_id','code'])->keyBy('code');
                $updates=[];$ts=now()->format('Y-m-d H:i:s');
                foreach($rows as $r){$errors=[];$status='valid';$reg=(string)($r->reg??'');$user=(string)($r->user_id??'');$code=(string)($r->code??'');
                    if($reg===''||!preg_match('/^\d{8}$/',$reg))$errors[]='REG must be an 8-digit registration number.';
                    if($user===''||strlen($user)>10)$errors[]='USER is missing or invalid.';
                    if($code==='')$errors[]='Viva code is required.';
                    if($code!==''&&isset($dupes[$code]))$errors[]='The same Viva code appears more than once in this import file.';
                    $registration=$registrations->get($reg); if(!$registration){$errors[]='Candidate was not found in Registration data.';} elseif((string)$registration->user_id!==$user){$errors[]='REG and USER do not belong to the same candidate.';$status='identity_conflict';}
                    $wr=$written->get($reg); if(!$wr || (string)$wr->user_id!==$user)$errors[]='Candidate is not in the current finalized Written-qualified result.';
                    $ex=$existing->get($code); if($ex && $registration && (int)$ex->registration_id!==(int)$registration->id)$errors[]='This Viva code is already assigned to another candidate.';
                    if($errors&&$status!=='identity_conflict')$status='invalid';
                    $updates[]=['id'=>$r->id,'batch_id'=>$r->batch_id,'source_row'=>$r->source_row,'raw_payload'=>$r->raw_payload,'registration_id'=>$registration?->id,'written_result_id'=>$wr?->id,'user_id'=>$r->user_id,'reg'=>$r->reg,'code'=>$r->code,'validation_status'=>$status,'validation_errors'=>$errors?json_encode($errors,JSON_UNESCAPED_UNICODE):null,'validation_warnings'=>null,'updated_at'=>$ts];
                    if($status==='valid')$valid++;elseif($status==='identity_conflict')$conflicts++;else$invalid++;
                }
                foreach(array_chunk($updates,1000) as $u)DB::connection('exam')->table('viva_mapping_import_staging')->upsert($u,['id'],['registration_id','written_result_id','validation_status','validation_errors','validation_warnings','updated_at']);
                $done+=count($rows);$batch->update(['processed_rows'=>$done,'valid_rows'=>$valid,'invalid_rows'=>$invalid,'identity_conflict_rows'=>$conflicts,'progress_percent'=>$total?min(99.9,round($done/$total*100,4)):100]);
            });
            $batch->update(['status'=>'validated','processed_rows'=>$done,'valid_rows'=>$valid,'warning_rows'=>0,'invalid_rows'=>$invalid,'identity_conflict_rows'=>$conflicts,'progress_percent'=>100,'validated_at'=>now(),'finished_at'=>now()]);return $batch->refresh();
        }catch(Throwable $e){$batch->update(['status'=>'failed','failure_message'=>mb_substr($e->getMessage(),0,65000),'finished_at'=>now()]);throw $e;}
    }
}
