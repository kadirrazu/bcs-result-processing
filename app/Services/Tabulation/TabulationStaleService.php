<?php
namespace App\Services\Tabulation;
use App\Models\TabulationFinalizationRun;use App\Models\TabulationProcessingState;use Illuminate\Support\Facades\Schema;
final class TabulationStaleService
{
    public function __construct(private readonly TabulationReadinessService $readiness,private readonly TabulationSourceSnapshotComparator $snapshotComparator){}
    public function synchronize():bool
    {
        if(!Schema::connection('exam')->hasTable('tabulation_processing_states'))return false;
        $state=TabulationProcessingState::query()->first();if(!$state||!$state->latest_run_id||!$state->source_snapshot)return false;
        $current=$this->readiness->inspect()['source_snapshot'];
        if(! $this->snapshotComparator->equivalent($state->source_snapshot,$current)){$this->mark('One or more finalized Registration/Preliminary/Written/Viva source datasets changed. Tabulation re-processing is mandatory.');return true;}
        return false;
    }
    public function mark(string $reason):void
    {
        if(!Schema::connection('exam')->hasTable('tabulation_processing_states'))return;
        $state=TabulationProcessingState::query()->first();if(!$state||!$state->latest_run_id)return;
        $state->update(['is_stale'=>true,'status'=>'stale','stale_reason'=>$reason,'finalized_at'=>null,'finalized_by'=>null]);
        if(Schema::connection('exam')->hasTable('tabulation_finalization_runs'))TabulationFinalizationRun::query()->where('status','current')->update(['status'=>'superseded']);
    }
}
