<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('exam')->create('written_import_batches', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('examination_id');
            $table->string('original_name');
            $table->string('stored_name');
            $table->string('status', 30)->default('queued')->index();
            foreach (['total_rows', 'processed_rows', 'staged_rows', 'valid_rows', 'warning_rows', 'invalid_rows', 'identity_conflict_rows', 'approved_rows', 'inserted_rows', 'updated_rows'] as $column) {
                $table->unsignedInteger($column)->default(0);
            }
            $table->decimal('progress_percent', 8, 4)->default(0);
            $table->longText('failure_message')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rolled_back_at')->nullable();
            $table->unsignedBigInteger('rolled_back_by')->nullable();
            $table->text('rollback_reason')->nullable();
            $table->timestamps();
        });

        Schema::connection('exam')->create('written_import_staging', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('batch_id')->index();
            $table->unsignedInteger('source_row');
            $table->json('raw_payload');
            $table->unsignedBigInteger('registration_id')->nullable()->index();
            $table->string('user_id', 10)->nullable()->index();
            $table->string('reg', 8)->nullable()->index();
            $table->json('normalized_marks')->nullable();
            $table->string('prs_code', 20)->nullable()->index();
            $table->decimal('prs_mark', 7, 2)->nullable();
            $table->text('data_source_note')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->string('validation_status', 30)->default('pending')->index();
            $table->json('validation_errors')->nullable();
            $table->json('validation_warnings')->nullable();
            $table->timestamps();
            $table->unique(['batch_id', 'source_row'], 'written_stage_batch_row_uq');
            $table->index(['batch_id', 'reg'], 'written_stage_batch_reg_idx');
            $table->index(['batch_id', 'user_id'], 'written_stage_batch_user_idx');
        });

        Schema::connection('exam')->create('written_results', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('registration_id')->unique();
            $table->string('user_id', 10)->unique();
            $table->string('reg', 8)->unique();
            $table->unsignedTinyInteger('cadre_category')->index();
            $table->string('prs_code', 20)->nullable()->index();
            $table->text('data_source_note')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->string('validation_status', 30)->default('pending')->index();
            $table->string('general_result_status', 20)->nullable()->index();
            $table->string('technical_result_status', 20)->nullable()->index();
            $table->string('written_qualified_track', 4)->nullable()->index();
            $table->decimal('general_actual_total', 8, 2)->nullable();
            $table->decimal('general_counted_total', 8, 2)->nullable();
            $table->decimal('technical_actual_total', 8, 2)->nullable();
            $table->decimal('technical_counted_total', 8, 2)->nullable();
            $table->json('general_fail_reasons')->nullable();
            $table->json('technical_fail_reasons')->nullable();
            $table->json('processing_flags')->nullable();
            $table->text('comment')->nullable();
            $table->unsignedBigInteger('source_batch_id')->index();
            $table->timestamp('finalized_at')->nullable();
            $table->unsignedBigInteger('last_edited_by')->nullable();
            $table->timestamp('last_edited_at')->nullable();
            $table->text('last_edit_reason')->nullable();
            $table->timestamps();
            $table->index(['status', 'validation_status'], 'written_result_status_validation_idx');
        });

        Schema::connection('exam')->create('written_candidate_marks', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('written_result_id')->index();
            $table->unsignedBigInteger('registration_id')->index();
            $table->string('subject_code', 10)->index();
            $table->string('raw_value', 100)->nullable();
            $table->decimal('actual_mark', 7, 2)->nullable();
            $table->decimal('counted_mark', 7, 2)->nullable();
            $table->string('attendance_status', 20)->nullable()->index();
            $table->boolean('paper_crashed')->default(false)->index();
            $table->decimal('crash_threshold', 7, 2)->nullable();
            $table->boolean('is_applicable')->default(false)->index();
            $table->boolean('has_warning')->default(false)->index();
            $table->json('warning_codes')->nullable();
            $table->timestamps();
            $table->unique(['written_result_id', 'subject_code'], 'written_result_subject_uq');
        });

        Schema::connection('exam')->create('written_processing_states', function (Blueprint $table): void {
            $table->id();
            $table->string('status', 40)->default('not_started');
            $table->unsignedBigInteger('latest_import_batch_id')->nullable();
            $table->unsignedBigInteger('reconciliation_generated_by')->nullable();
            $table->timestamp('reconciliation_generated_at')->nullable();
            $table->unsignedBigInteger('result_finalized_by')->nullable();
            $table->timestamp('result_finalized_at')->nullable();
            $table->json('summary')->nullable();
            $table->boolean('is_stale')->default(false)->index();
            $table->text('stale_reason')->nullable();
            $table->timestamps();
        });

        Schema::connection('exam')->create('written_processing_audits', function (Blueprint $table): void {
            $table->id();
            $table->string('action', 100)->index();
            $table->string('status_before', 40)->nullable();
            $table->string('status_after', 40)->nullable();
            $table->unsignedBigInteger('batch_id')->nullable()->index();
            $table->unsignedBigInteger('processing_run_id')->nullable()->index();
            $table->unsignedBigInteger('registration_id')->nullable()->index();
            $table->unsignedBigInteger('written_result_id')->nullable()->index();
            $table->unsignedBigInteger('actor_id');
            $table->string('actor_name')->nullable();
            $table->text('reason')->nullable();
            $table->json('changed_fields')->nullable();
            $table->json('summary')->nullable();
            $table->json('before_snapshot')->nullable();
            $table->json('after_snapshot')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        foreach ([
            'written_processing_audits',
            'written_processing_states',
            'written_candidate_marks',
            'written_results',
            'written_import_staging',
            'written_import_batches',
        ] as $table) {
            Schema::connection('exam')->dropIfExists($table);
        }
    }
};
