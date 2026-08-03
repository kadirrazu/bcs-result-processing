<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('exam')->create('viva_import_batches', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('examination_id');
            $table->string('import_type', 20)->index(); // mapping | board
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
            $table->index(['import_type', 'status'], 'viva_batch_type_status_idx');
        });

        Schema::connection('exam')->create('viva_mapping_import_staging', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('batch_id')->index();
            $table->unsignedInteger('source_row');
            $table->json('raw_payload');
            $table->unsignedBigInteger('registration_id')->nullable()->index();
            $table->unsignedBigInteger('written_result_id')->nullable()->index();
            $table->string('user_id', 10)->nullable()->index();
            $table->string('reg', 8)->nullable()->index();
            $table->string('code', 100)->nullable()->index();
            $table->string('validation_status', 30)->default('pending')->index();
            $table->json('validation_errors')->nullable();
            $table->json('validation_warnings')->nullable();
            $table->timestamps();
            $table->unique(['batch_id', 'source_row'], 'viva_map_stage_batch_row_uq');
            $table->index(['batch_id', 'code'], 'viva_map_stage_batch_code_idx');
        });

        Schema::connection('exam')->create('viva_candidate_mappings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('registration_id')->unique();
            $table->unsignedBigInteger('written_result_id')->unique();
            $table->string('user_id', 10)->unique();
            $table->string('reg', 8)->unique();
            $table->string('code', 100)->unique();
            $table->unsignedBigInteger('source_batch_id')->index();
            $table->timestamps();
        });

        Schema::connection('exam')->create('viva_board_import_staging', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('batch_id')->index();
            $table->unsignedInteger('source_row');
            $table->json('raw_payload');
            $table->unsignedBigInteger('viva_candidate_mapping_id')->nullable()->index();
            $table->unsignedBigInteger('registration_id')->nullable()->index();
            $table->string('code', 100)->nullable()->index();
            $table->string('raw_viva_date', 50)->nullable();
            $table->date('viva_date')->nullable()->index();
            $table->string('member_id', 100)->nullable()->index();
            $table->string('raw_mark', 100)->nullable();
            $table->decimal('mark', 7, 2)->nullable();
            $table->string('attendance_status', 20)->nullable()->index();
            $table->string('raw_viva_cff', 100)->nullable();
            $table->string('raw_viva_em', 100)->nullable();
            $table->string('raw_viva_phc', 100)->nullable();
            $table->boolean('viva_cff')->default(false)->index();
            $table->boolean('viva_em')->default(false)->index();
            $table->boolean('viva_phc')->default(false)->index();
            $table->string('raw_invalid_flag', 100)->nullable();
            $table->string('raw_issue_flag', 100)->nullable();
            $table->boolean('invalid_flag')->default(false)->index();
            $table->boolean('issue_flag')->default(false)->index();
            $table->string('validation_status', 30)->default('pending')->index();
            $table->json('validation_errors')->nullable();
            $table->json('validation_warnings')->nullable();
            $table->timestamps();
            $table->unique(['batch_id', 'source_row'], 'viva_board_stage_batch_row_uq');
            $table->index(['batch_id', 'code'], 'viva_board_stage_batch_code_idx');
        });

        Schema::connection('exam')->create('viva_results', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('viva_candidate_mapping_id')->unique();
            $table->unsignedBigInteger('registration_id')->unique();
            $table->unsignedBigInteger('written_result_id')->unique();
            $table->string('user_id', 10)->unique();
            $table->string('reg', 8)->unique();
            $table->string('code', 100)->unique();
            $table->unsignedTinyInteger('cadre_category')->index();
            $table->string('written_qualified_track', 4)->index();
            $table->string('raw_viva_date', 50)->nullable();
            $table->date('viva_date')->nullable()->index();
            $table->string('member_id', 100)->nullable()->index();
            $table->string('raw_mark', 100)->nullable();
            $table->decimal('mark', 7, 2)->nullable();
            $table->string('attendance_status', 20)->nullable()->index();
            $table->string('raw_viva_cff', 100)->nullable();
            $table->string('raw_viva_em', 100)->nullable();
            $table->string('raw_viva_phc', 100)->nullable();
            $table->boolean('viva_cff')->default(false)->index();
            $table->boolean('viva_em')->default(false)->index();
            $table->boolean('viva_phc')->default(false)->index();
            $table->string('raw_invalid_flag', 100)->nullable();
            $table->string('raw_issue_flag', 100)->nullable();
            $table->boolean('invalid_flag')->default(false)->index();
            $table->boolean('issue_flag')->default(false)->index();
            $table->boolean('quota_mismatch')->default(false)->index();
            $table->json('quota_mismatch_details')->nullable();
            $table->boolean('high_mark_review')->default(false)->index();
            $table->string('status', 20)->default('active')->index();
            $table->string('validation_status', 30)->default('pending')->index();
            $table->string('viva_result_status', 20)->default('pending')->index();
            $table->text('comment')->nullable();
            $table->unsignedBigInteger('source_batch_id')->index();
            $table->timestamp('finalized_at')->nullable();
            $table->unsignedBigInteger('last_edited_by')->nullable();
            $table->timestamp('last_edited_at')->nullable();
            $table->text('last_edit_reason')->nullable();
            $table->timestamps();
            $table->index(['status', 'validation_status'], 'viva_result_status_validation_idx');
        });

        Schema::connection('exam')->create('viva_processing_states', function (Blueprint $table): void {
            $table->id();
            $table->string('status', 40)->default('not_started');
            $table->unsignedBigInteger('latest_mapping_batch_id')->nullable();
            $table->unsignedBigInteger('latest_board_batch_id')->nullable();
            $table->unsignedBigInteger('reconciliation_generated_by')->nullable();
            $table->timestamp('reconciliation_generated_at')->nullable();
            $table->unsignedBigInteger('result_processed_by')->nullable();
            $table->timestamp('result_processed_at')->nullable();
            $table->unsignedBigInteger('result_finalized_by')->nullable();
            $table->timestamp('result_finalized_at')->nullable();
            $table->json('summary')->nullable();
            $table->boolean('is_stale')->default(false)->index();
            $table->text('stale_reason')->nullable();
            $table->timestamps();
        });

        Schema::connection('exam')->create('viva_processing_audits', function (Blueprint $table): void {
            $table->id();
            $table->string('action', 100)->index();
            $table->string('status_before', 40)->nullable();
            $table->string('status_after', 40)->nullable();
            $table->unsignedBigInteger('batch_id')->nullable()->index();
            $table->unsignedBigInteger('registration_id')->nullable()->index();
            $table->unsignedBigInteger('viva_result_id')->nullable()->index();
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
            'viva_processing_audits',
            'viva_processing_states',
            'viva_results',
            'viva_board_import_staging',
            'viva_candidate_mappings',
            'viva_mapping_import_staging',
            'viva_import_batches',
        ] as $table) {
            Schema::connection('exam')->dropIfExists($table);
        }
    }
};
