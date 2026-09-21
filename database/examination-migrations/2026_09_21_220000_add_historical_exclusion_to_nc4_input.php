<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $schema = Schema::connection('exam');

        if (! $schema->hasColumn('non_cadre_allocation_input_candidates', 'historical_excluded')) {
            $schema->table('non_cadre_allocation_input_candidates', function (Blueprint $table): void {
                $table->boolean('historical_excluded')->default(false)->after('allocation_ready_choices')->index('nc4_input_hist_ex_idx');
            });
        }
        if (! $schema->hasColumn('non_cadre_allocation_input_candidates', 'historical_exclusion_reason')) {
            $schema->table('non_cadre_allocation_input_candidates', function (Blueprint $table): void {
                $table->text('historical_exclusion_reason')->nullable()->after('historical_excluded');
            });
        }
        if (! $schema->hasColumn('non_cadre_allocation_input_candidates', 'historical_exclusion_evidence')) {
            $schema->table('non_cadre_allocation_input_candidates', function (Blueprint $table): void {
                $table->json('historical_exclusion_evidence')->nullable()->after('historical_exclusion_reason');
            });
        }

        // This is an examination-database business-rule revision. Existing current
        // NC4 output was produced without the historical exclusion gate and must
        // be re-frozen/reprocessed before NC5 can be authoritative again.
        DB::connection('exam')->table('non_cadre_allocation_runs')->where('is_stale', false)->update([
            'is_stale' => true,
            'stale_reason' => 'NC4 historical recommendation/employment exclusion rule introduced; re-freeze and re-run allocation.',
            'staled_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('exam')->table('non_cadre_processing_states')->where('id', 1)->update([
            'allocation_status' => 'stale',
            'reporting_status' => 'not_started',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        $schema = Schema::connection('exam');
        if (! $schema->hasTable('non_cadre_allocation_input_candidates')) {
            return;
        }

        if ($schema->hasColumn('non_cadre_allocation_input_candidates', 'historical_exclusion_evidence')) {
            $schema->table('non_cadre_allocation_input_candidates', fn (Blueprint $table) => $table->dropColumn('historical_exclusion_evidence'));
        }
        if ($schema->hasColumn('non_cadre_allocation_input_candidates', 'historical_exclusion_reason')) {
            $schema->table('non_cadre_allocation_input_candidates', fn (Blueprint $table) => $table->dropColumn('historical_exclusion_reason'));
        }
        if ($schema->hasColumn('non_cadre_allocation_input_candidates', 'historical_excluded')) {
            $schema->table('non_cadre_allocation_input_candidates', function (Blueprint $table): void {
                $table->dropIndex('nc4_input_hist_ex_idx');
                $table->dropColumn('historical_excluded');
            });
        }
    }
};
