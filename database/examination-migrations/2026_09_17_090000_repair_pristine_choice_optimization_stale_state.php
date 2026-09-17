<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('exam');

        if (! $schema->hasTable('choice_optimization_processing_states')) {
            return;
        }

        $workflowChangeReason = 'CHOICE_OPTIMIZATION_WORKFLOW_CHANGED_2026_09_07: track mismatch is deferred from Choice Validation; Choice Optimization is mandatory; selected historical sources are consolidated once; Written-track Filter runs last.';

        // Repair only the false-positive state created on pristine databases by the
        // 2026-09-07 upgrade migration. Never clear a genuinely produced/stale dataset.
        DB::connection('exam')->table('choice_optimization_processing_states')
            ->where('status', 'not_started')
            ->where('is_stale', true)
            ->where('stale_reason', $workflowChangeReason)
            ->whereNull('dataset_hash')
            ->whereNull('source_snapshot')
            ->whereNull('summary')
            ->whereNull('finalized_by')
            ->whereNull('finalized_at')
            ->update([
                'is_stale' => false,
                'stale_reason' => null,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Data repair is intentionally irreversible. Reintroducing a false stale state
        // on a pristine examination would violate the processing-state contract.
    }
};
