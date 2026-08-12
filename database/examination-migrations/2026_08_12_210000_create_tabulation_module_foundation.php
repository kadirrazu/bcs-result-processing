<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('exam');

        $schema->create('tabulation_processing_states', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->string('status', 40)->default('not_started')->index();
            $table->unsignedBigInteger('latest_run_id')->nullable()->index();
            $table->unsignedBigInteger('latest_finalization_run_id')->nullable()->index();
            $table->boolean('is_stale')->default(false)->index();
            $table->text('stale_reason')->nullable();
            $table->json('source_snapshot')->nullable();
            $table->json('summary')->nullable();
            $table->unsignedBigInteger('finalized_by')->nullable()->index();
            $table->timestamp('finalized_at')->nullable()->index();
            $table->timestamps();
        });

        $schema->create('tabulation_processing_runs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('processing_version')->unique('tab_proc_ver_uq');
            $table->string('status', 30)->default('queued')->index();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('valid_rows')->default(0);
            $table->unsignedInteger('warning_rows')->default(0);
            $table->unsignedInteger('error_rows')->default(0);
            $table->unsignedInteger('general_pass_count')->default(0);
            $table->unsignedInteger('technical_pass_count')->default(0);
            $table->unsignedInteger('general_merit_eligible_count')->default(0);
            $table->unsignedInteger('technical_merit_eligible_count')->default(0);
            $table->decimal('progress_percent', 8, 4)->default(0);
            $table->string('current_step', 160)->nullable();
            $table->json('source_snapshot');
            $table->json('rule_snapshot');
            $table->json('summary')->nullable();
            $table->longText('failure_message')->nullable();
            $table->unsignedBigInteger('created_by')->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        $schema->create('tabulation_results', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('processing_run_id')->index();
            $table->unsignedInteger('processing_version')->index();
            $table->unsignedBigInteger('registration_id')->index();
            $table->unsignedBigInteger('preliminary_result_id')->nullable()->index();
            $table->unsignedBigInteger('written_result_id')->index();
            $table->unsignedBigInteger('viva_result_id')->index();
            $table->string('user_id', 20)->index();
            $table->string('reg', 20)->index();
            $table->string('written_qualified_track', 10)->nullable()->index();
            $table->decimal('preliminary_mark', 8, 2)->nullable();
            $table->decimal('general_written_total', 8, 2)->nullable();
            $table->decimal('technical_written_total', 8, 2)->nullable();
            $table->decimal('viva_mark', 8, 2);
            $table->decimal('general_grand_total', 8, 2)->nullable();
            $table->decimal('technical_grand_total', 8, 2)->nullable();
            $table->string('general_pf', 20)->default('N/A')->index();
            $table->string('technical_pf', 20)->default('N/A')->index();
            $table->boolean('general_merit_eligible')->default(false)->index();
            $table->boolean('technical_merit_eligible')->default(false)->index();
            $table->string('validation_status', 20)->default('valid')->index();
            $table->json('validation_errors')->nullable();
            $table->json('review_warnings')->nullable();
            $table->json('source_snapshot');
            $table->json('processing_flags')->nullable();
            $table->timestamp('processed_at')->index();
            $table->timestamps();
            $table->unique(['processing_run_id', 'registration_id'], 'tab_run_registration_uq');
            $table->foreign('processing_run_id', 'tab_result_run_fk')->references('id')->on('tabulation_processing_runs')->cascadeOnDelete();
        });

        $schema->create('tabulation_finalization_runs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('processing_run_id')->index();
            $table->unsignedInteger('processing_version')->index();
            $table->string('status', 30)->default('current')->index();
            $table->json('source_snapshot');
            $table->json('summary');
            $table->unsignedBigInteger('finalized_by')->index();
            $table->timestamp('finalized_at')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->foreign('processing_run_id', 'tab_final_run_fk')->references('id')->on('tabulation_processing_runs')->restrictOnDelete();
        });

        $schema->create('tabulation_processing_audits', function (Blueprint $table): void {
            $table->id();
            $table->string('event', 80)->index();
            $table->unsignedBigInteger('processing_run_id')->nullable()->index();
            $table->unsignedBigInteger('actor_id')->nullable()->index();
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40)->nullable();
            $table->text('reason')->nullable();
            $table->json('summary')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        $schema = Schema::connection('exam');
        $schema->dropIfExists('tabulation_processing_audits');
        $schema->dropIfExists('tabulation_finalization_runs');
        $schema->dropIfExists('tabulation_results');
        $schema->dropIfExists('tabulation_processing_runs');
        $schema->dropIfExists('tabulation_processing_states');
    }
};
