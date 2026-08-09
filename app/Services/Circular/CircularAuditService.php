<?php
namespace App\Services\Circular;
use App\Models\CircularProcessingAudit; use App\Models\User; use Illuminate\Support\Facades\Log;
final class CircularAuditService
{
 public function record(string $action,User $actor,?string $reason=null,array $changedFields=[],?array $before=null,?array $after=null,array $summary=[]):CircularProcessingAudit
 {
  $context=['action'=>$action,'actor_id'=>(int)$actor->id,'actor_name'=>(string)$actor->name,'reason'=>$reason,'changed_fields'=>$changedFields,'before'=>$before,'after'=>$after,'summary'=>$summary];
  $audit=CircularProcessingAudit::query()->create([...$context,'before_snapshot'=>$before,'after_snapshot'=>$after,'created_at'=>now()]);Log::channel('circular')->info('Circular processing action recorded.',$context);return $audit;
 }
}
