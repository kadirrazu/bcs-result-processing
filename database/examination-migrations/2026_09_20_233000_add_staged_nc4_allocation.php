<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $s = Schema::connection('exam');

        // This migration may be re-run after a failed MySQL CREATE TABLE.  The
        // ALTER on non_cadre_allocation_runs can already have succeeded, so add
        // every run column only when it is still missing.
        $runColumns = [
            'phase' => fn (Blueprint $t) => $t->string('phase', 30)->default('INPUT_FREEZE')->index('nc4_run_phase_idx')->after('status'),
            'freeze_hash' => fn (Blueprint $t) => $t->string('freeze_hash', 64)->nullable()->index('nc4_run_freeze_hash_idx')->after('input_hash'),
            'phase1_hash' => fn (Blueprint $t) => $t->string('phase1_hash', 64)->nullable()->index('nc4_run_phase1_hash_idx')->after('freeze_hash'),
            'phase2_hash' => fn (Blueprint $t) => $t->string('phase2_hash', 64)->nullable()->index('nc4_run_phase2_hash_idx')->after('phase1_hash'),
            'validation_hash' => fn (Blueprint $t) => $t->string('validation_hash', 64)->nullable()->index('nc4_run_validation_hash_idx')->after('phase2_hash'),
            'nm_count' => fn (Blueprint $t) => $t->unsignedInteger('nm_count')->default(0)->after('pending_special_reviews'),
            'shifted_count' => fn (Blueprint $t) => $t->unsignedInteger('shifted_count')->default(0)->after('nm_count'),
            'quota_to_merit_count' => fn (Blueprint $t) => $t->unsignedInteger('quota_to_merit_count')->default(0)->after('shifted_count'),
        ];

        foreach ($runColumns as $column => $definition) {
            if (! $s->hasColumn('non_cadre_allocation_runs', $column)) {
                $s->table('non_cadre_allocation_runs', function (Blueprint $t) use ($definition): void {
                    $definition($t);
                });
            }
        }

        // These are NC4-R2-only staging tables. If the first attempt failed
        // while MySQL was creating an index, remove any incomplete remnants and
        // recreate them with deliberately short identifier names (< 64 chars).
        foreach ([
            'non_cadre_allocation_validation_checks',
            'non_cadre_allocation_phase2_results',
            'non_cadre_allocation_phase1_results',
            'non_cadre_allocation_input_candidates',
        ] as $table) {
            $s->dropIfExists($table);
        }

        $s->create('non_cadre_allocation_input_candidates', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('allocation_run_id')->index('nc4_input_run_idx');
            $t->unsignedBigInteger('registration_id')->index('nc4_input_regid_idx');
            $t->string('reg', 20)->index('nc4_input_reg_idx');
            $t->unsignedInteger('common_merit_position')->index('nc4_input_cmp_idx');
            $t->boolean('has_cff')->default(false);
            $t->boolean('has_em')->default(false);
            $t->boolean('has_phc')->default(false);
            $t->json('allocation_ready_choices');
            $t->timestamps();
            $t->unique(['allocation_run_id', 'registration_id'], 'nc4_input_candidate_uq');
            $t->foreign('allocation_run_id', 'nc4_input_run_fk')->references('id')->on('non_cadre_allocation_runs')->cascadeOnDelete();
        });

        foreach (['phase1', 'phase2'] as $phase) {
            $p = $phase === 'phase1' ? 'nc4_p1' : 'nc4_p2';
            $s->create("non_cadre_allocation_{$phase}_results", function (Blueprint $t) use ($phase, $p): void {
                $t->id();
                $t->unsignedBigInteger('allocation_run_id')->index("{$p}_run_idx");
                $t->unsignedBigInteger('registration_id')->index("{$p}_regid_idx");
                $t->string('reg', 20)->index("{$p}_reg_idx");
                $t->unsignedInteger('common_merit_position')->index("{$p}_cmp_idx");
                $t->unsignedBigInteger('circular_post_id')->nullable()->index("{$p}_postid_idx");
                $t->string('post_code', 50)->nullable()->index("{$p}_postcode_idx");
                $t->unsignedSmallInteger('choice_position')->nullable();
                $t->string('allocation_basis', 10)->nullable()->index("{$p}_basis_idx");
                $t->string('movement_type', 30)->nullable()->index("{$p}_move_idx");
                $t->text('decision_reason')->nullable();
                $t->timestamps();
                $t->unique(['allocation_run_id', 'registration_id'], "{$p}_candidate_uq");
                $t->foreign('allocation_run_id', "{$p}_run_fk")->references('id')->on('non_cadre_allocation_runs')->cascadeOnDelete();
            });
        }

        $s->create('non_cadre_allocation_validation_checks', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('allocation_run_id')->index('nc4_val_run_idx');
            $t->string('check_code', 80)->index('nc4_val_code_idx');
            $t->string('status', 10)->index('nc4_val_status_idx');
            $t->text('message');
            $t->json('context')->nullable();
            $t->timestamps();
            $t->foreign('allocation_run_id', 'nc4_validation_run_fk')->references('id')->on('non_cadre_allocation_runs')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        $s = Schema::connection('exam');
        foreach (['non_cadre_allocation_validation_checks', 'non_cadre_allocation_phase2_results', 'non_cadre_allocation_phase1_results', 'non_cadre_allocation_input_candidates'] as $x) {
            $s->dropIfExists($x);
        }

        if ($s->hasTable('non_cadre_allocation_runs')) {
            $columns = array_values(array_filter([
                'phase', 'freeze_hash', 'phase1_hash', 'phase2_hash', 'validation_hash',
                'nm_count', 'shifted_count', 'quota_to_merit_count',
            ], fn (string $column): bool => $s->hasColumn('non_cadre_allocation_runs', $column)));

            if ($columns !== []) {
                $s->table('non_cadre_allocation_runs', function (Blueprint $t) use ($columns): void {
                    $t->dropColumn($columns);
                });
            }
        }
    }
};
