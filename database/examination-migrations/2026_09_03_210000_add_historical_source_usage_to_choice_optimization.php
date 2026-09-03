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

        // A failed migration can leave this column physically created even though Laravel did
        // not record the migration as completed. Keep the migration retry-safe so operators
        // can simply run examination:migrate again after fixing the deployment package.
        if (! $schema->hasColumn('choice_optimization_historical_sources', 'included_in_optimization')) {
            $schema->table('choice_optimization_historical_sources', function (Blueprint $table): void {
                // Existing pulled sources preserve the old behavior by default: INCLUDED.
                // Operators may later exclude a source without deleting its pulled/matched evidence.
                $table->boolean('included_in_optimization')
                    ->default(true)
                    ->index('co_hist_src_use_idx')
                    ->after('status');
            });
        }

        // The source-authority contract changed: Google Form is now latest-batch-only.
        // Any previously produced/finalized optimization output may have consumed older batches,
        // so it must be explicitly regenerated before Allocation can trust it again.
        if ($schema->hasTable('choice_optimization_processing_states')) {
            DB::connection('exam')->table('choice_optimization_processing_states')
                ->where(function ($q): void {
                    $q->whereNotNull('dataset_hash')->orWhere('status', 'finalized');
                })
                ->update([
                    'is_stale' => true,
                    'stale_reason' => 'Choice Optimization source authority changed: Google Form now uses latest approved batch only. Re-process Optimization.',
                    'finalized_by' => null,
                    'finalized_at' => null,
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('exam');

        if ($schema->hasColumn('choice_optimization_historical_sources', 'included_in_optimization')) {
            $schema->table('choice_optimization_historical_sources', function (Blueprint $table): void {
                // The named index is created together with the column in up().
                $table->dropIndex('co_hist_src_use_idx');
                $table->dropColumn('included_in_optimization');
            });
        }
    }
};
