<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('exam');

        $schema->table('choice_optimization_historical_choices', function (Blueprint $table): void {
            $table->json('post_historical_choice_codes')->nullable()->after('removed_choice_codes');
            $table->json('track_removed_choice_codes')->nullable()->after('post_historical_choice_codes');
            $table->json('track_filter_details')->nullable()->after('track_removed_choice_codes');
            $table->string('track_filter_status', 64)->nullable()->index('co_hist_track_status_idx')->after('track_filter_details');
        });

        if ($schema->hasTable('choice_optimization_settings')) {
            DB::connection('exam')->table('choice_optimization_settings')->where('id', 1)->update([
                'optimization_enabled' => true,
                'updated_at' => now(),
            ]);
        }

        $this->markResultPipelineStale($schema);
    }

    private function markResultPipelineStale($schema): void
    {
        $reason = 'CHOICE_OPTIMIZATION_WORKFLOW_CHANGED_2026_09_07: track mismatch is deferred from Choice Validation; Choice Optimization is mandatory; selected historical sources are consolidated once; Written-track Filter runs last.';
        $now = now();

        if ($schema->hasTable('choice_validation_processing_states')) {
            DB::connection('exam')->table('choice_validation_processing_states')
                ->where('current_validation_version', '>', 0)
                ->update(['status' => 'stale', 'is_stale' => true, 'stale_reason' => $reason, 'updated_at' => $now]);
        }
        if ($schema->hasTable('merit_processing_states')) {
            DB::connection('exam')->table('merit_processing_states')
                ->whereNotNull('latest_run_id')
                ->update(['status' => 'stale', 'is_stale' => true, 'stale_reason' => $reason, 'updated_at' => $now]);
        }
        if ($schema->hasTable('choice_optimization_processing_states')) {
            DB::connection('exam')->table('choice_optimization_processing_states')->update([
                'is_stale' => true, 'stale_reason' => $reason,
                'finalized_by' => null, 'finalized_at' => null, 'updated_at' => $now,
            ]);
        }
        if ($schema->hasTable('allocation_processing_states')) {
            DB::connection('exam')->table('allocation_processing_states')
                ->where('status', '<>', 'not_started')
                ->update(['is_stale' => true, 'stale_reason' => $reason, 'finalized_by' => null, 'finalized_at' => null, 'updated_at' => $now]);
        }
        if ($schema->hasTable('allocation_input_freezes')) {
            DB::connection('exam')->table('allocation_input_freezes')->where('status', 'frozen')->update(['status' => 'stale', 'updated_at' => $now]);
        }
        foreach (['allocation_runs', 'allocation_a4_runs', 'allocation_a5_runs'] as $table) {
            if (! $schema->hasTable($table) || ! $schema->hasColumn($table, 'is_stale')) continue;
            $updates = ['is_stale' => true, 'stale_reason' => $reason, 'updated_at' => $now];
            if ($schema->hasColumn($table, 'staled_at')) $updates['staled_at'] = $now;
            DB::connection('exam')->table($table)->where('is_stale', false)->update($updates);
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('exam');
        $schema->table('choice_optimization_historical_choices', function (Blueprint $table): void {
            $table->dropIndex('co_hist_track_status_idx');
            $table->dropColumn(['post_historical_choice_codes', 'track_removed_choice_codes', 'track_filter_details', 'track_filter_status']);
        });
    }
};
