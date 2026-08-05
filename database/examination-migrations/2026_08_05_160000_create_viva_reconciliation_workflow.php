<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('exam')->create('viva_reconciliation_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('status', 30)->default('queued')->index();
            $table->unsignedInteger('total_candidates')->default(0);
            $table->unsignedInteger('processed_candidates')->default(0);
            $table->decimal('progress_percent', 8, 4)->default(0);
            foreach ([
                'eligible_count', 'mapped_count', 'board_data_count', 'missing_mapping_count', 'missing_board_count',
                'appeared_count', 'absent_count', 'active_count', 'cancelled_count', 'withheld_count', 'expelled_count',
                'warning_count', 'quota_mismatch_count', 'quota_cff_mismatch_count', 'quota_em_mismatch_count',
                'quota_phc_mismatch_count', 'source_invalid_count', 'source_issue_count', 'high_mark_count',
            ] as $column) {
                $table->unsignedInteger($column)->default(0);
            }
            $table->json('track_summary')->nullable();
            $table->json('category_summary')->nullable();
            $table->json('review_summary')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->longText('failure_message')->nullable();
            $table->timestamps();
        });

        Schema::connection('exam')->table('viva_processing_states', function (Blueprint $table): void {
            $table->unsignedBigInteger('latest_reconciliation_run_id')->nullable()->after('latest_board_batch_id');
            $table->index('latest_reconciliation_run_id', 'viva_state_recon_run_idx');
        });
    }

    public function down(): void
    {
        Schema::connection('exam')->table('viva_processing_states', function (Blueprint $table): void {
            $table->dropIndex('viva_state_recon_run_idx');
            $table->dropColumn('latest_reconciliation_run_id');
        });

        Schema::connection('exam')->dropIfExists('viva_reconciliation_runs');
    }
};
