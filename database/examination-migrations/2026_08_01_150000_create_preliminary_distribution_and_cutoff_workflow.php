<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CONNECTION = 'exam';

    public function up(): void
    {
        $schema = Schema::connection(self::CONNECTION);

        if (! $schema->hasTable('preliminary_distribution_reports')) {
            $schema->create('preliminary_distribution_reports', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('import_batch_id')->nullable()->index('prelim_dist_batch_idx');
                $table->unsignedBigInteger('reconciliation_report_id')->nullable()->index('prelim_dist_recon_idx');
                $table->unsignedInteger('eligible_candidates')->default(0);
                $table->unsignedInteger('gg_candidates')->default(0);
                $table->unsignedInteger('tt_candidates')->default(0);
                $table->unsignedInteger('gt_candidates')->default(0);
                $table->unsignedInteger('distinct_marks')->default(0);
                $table->decimal('minimum_mark', 6, 2)->nullable();
                $table->decimal('maximum_mark', 6, 2)->nullable();
                $table->json('distribution');
                $table->unsignedBigInteger('generated_by');
                $table->timestamp('generated_at');
                $table->timestamps();
            });
        }

        if (! $schema->hasTable('preliminary_cutoff_decisions')) {
            $schema->create('preliminary_cutoff_decisions', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('distribution_report_id')->index('prelim_cutoff_dist_idx');
                $table->decimal('cutoff_mark', 6, 2);
                $table->string('status', 20)->default('proposed')->index('prelim_cutoff_status_idx');
                $table->text('reason');
                $table->unsignedBigInteger('proposed_by');
                $table->timestamp('proposed_at');
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->text('approval_reason')->nullable();
                $table->unsignedInteger('pass_total')->default(0);
                $table->unsignedInteger('pass_gg')->default(0);
                $table->unsignedInteger('pass_tt')->default(0);
                $table->unsignedInteger('pass_gt')->default(0);
                $table->json('snapshot')->nullable();
                $table->timestamps();
            });
        }

        $columns = [
            'latest_distribution_report_id' => function (Blueprint $table): void {
                $table->unsignedBigInteger('latest_distribution_report_id')->nullable()->after('latest_reconciliation_report_id');
                $table->index('latest_distribution_report_id', 'prelim_state_dist_idx');
            },
            'current_cutoff_decision_id' => function (Blueprint $table): void {
                $table->unsignedBigInteger('current_cutoff_decision_id')->nullable()->after('latest_distribution_report_id');
                $table->index('current_cutoff_decision_id', 'prelim_state_cutoff_idx');
            },
            'cutoff_requires_review' => function (Blueprint $table): void {
                $table->boolean('cutoff_requires_review')->default(false)->after('cutoff_set_at');
            },
            'distribution_generated_by' => function (Blueprint $table): void {
                $table->unsignedBigInteger('distribution_generated_by')->nullable()->after('reconciliation_generated_at');
            },
            'distribution_generated_at' => function (Blueprint $table): void {
                $table->timestamp('distribution_generated_at')->nullable()->after('distribution_generated_by');
            },
        ];

        foreach ($columns as $name => $definition) {
            if (! $schema->hasColumn('preliminary_processing_states', $name)) {
                $schema->table('preliminary_processing_states', $definition);
            }
        }
    }

    public function down(): void
    {
        $schema = Schema::connection(self::CONNECTION);

        if ($schema->hasTable('preliminary_processing_states')) {
            foreach ([
                ['distribution_generated_at', null],
                ['distribution_generated_by', null],
                ['cutoff_requires_review', null],
                ['current_cutoff_decision_id', 'prelim_state_cutoff_idx'],
                ['latest_distribution_report_id', 'prelim_state_dist_idx'],
            ] as [$column, $index]) {
                if ($schema->hasColumn('preliminary_processing_states', $column)) {
                    $schema->table('preliminary_processing_states', function (Blueprint $table) use ($column, $index): void {
                        if ($index !== null) {
                            $table->dropIndex($index);
                        }
                        $table->dropColumn($column);
                    });
                }
            }
        }

        $schema->dropIfExists('preliminary_cutoff_decisions');
        $schema->dropIfExists('preliminary_distribution_reports');
    }
};
