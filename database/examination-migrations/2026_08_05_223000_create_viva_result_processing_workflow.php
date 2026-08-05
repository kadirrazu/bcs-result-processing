<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('exam')->create('viva_processing_runs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('processing_version')->index();
            $table->string('status', 30)->default('queued')->index();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('academic_processed_count')->default(0);
            $table->unsignedInteger('pass_count')->default(0);
            $table->unsignedInteger('fail_count')->default(0);
            $table->unsignedInteger('absent_count')->default(0);
            $table->unsignedInteger('cancelled_count')->default(0);
            $table->unsignedInteger('withheld_count')->default(0);
            $table->unsignedInteger('expelled_count')->default(0);
            $table->decimal('progress_percent', 8, 4)->default(0);
            $table->decimal('full_mark', 7, 2);
            $table->decimal('pass_percent', 7, 2);
            $table->decimal('pass_mark', 7, 2);
            $table->string('current_step', 150)->nullable();
            $table->json('summary')->nullable();
            $table->longText('failure_message')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->unique('processing_version', 'viva_processing_version_uq');
        });

        Schema::connection('exam')->table('viva_results', function (Blueprint $table): void {
            $table->json('viva_fail_reasons')->nullable()->after('viva_result_status');
            $table->json('processing_snapshot')->nullable()->after('viva_fail_reasons');
            $table->unsignedInteger('processing_version')->nullable()->index()->after('processing_snapshot');
            $table->unsignedBigInteger('processing_run_id')->nullable()->index()->after('processing_version');
            $table->unsignedBigInteger('processed_by')->nullable()->after('processing_run_id');
            $table->timestamp('processed_at')->nullable()->index()->after('processed_by');
        });

        Schema::connection('exam')->table('viva_processing_states', function (Blueprint $table): void {
            $table->unsignedBigInteger('latest_processing_run_id')->nullable()->index('viva_state_latest_process_idx')->after('latest_reconciliation_run_id');
        });
    }

    public function down(): void
    {
        Schema::connection('exam')->table('viva_processing_states', function (Blueprint $table): void {
            $table->dropIndex('viva_state_latest_process_idx');
            $table->dropColumn('latest_processing_run_id');
        });

        Schema::connection('exam')->table('viva_results', function (Blueprint $table): void {
            $table->dropColumn([
                'viva_fail_reasons', 'processing_snapshot', 'processing_version',
                'processing_run_id', 'processed_by', 'processed_at',
            ]);
        });

        Schema::connection('exam')->dropIfExists('viva_processing_runs');
    }
};
