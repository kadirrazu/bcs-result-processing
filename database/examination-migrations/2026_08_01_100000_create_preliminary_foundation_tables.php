<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('exam')->create('preliminary_import_batches', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('examination_id');
            $table->string('original_name');
            $table->string('stored_name');
            $table->string('status', 30)->default('queued')->index();
            foreach (['total_rows','processed_rows','staged_rows','valid_rows','warning_rows','invalid_rows','identity_conflict_rows','approved_rows','inserted_rows','updated_rows'] as $column) {
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

        Schema::connection('exam')->create('preliminary_import_staging', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('batch_id')->index();
            $table->unsignedInteger('source_row');
            $table->string('raw_user', 100)->nullable();
            $table->string('raw_reg', 100)->nullable();
            $table->string('raw_mark', 100)->nullable();
            $table->text('raw_candidate_status')->nullable();
            $table->unsignedBigInteger('registration_id')->nullable()->index();
            $table->string('user_id', 10)->nullable()->index();
            $table->string('reg', 8)->nullable()->index();
            $table->decimal('mark', 6, 2)->nullable()->index();
            $table->string('candidate_status', 20)->nullable()->index();
            $table->string('validation_status', 30)->default('pending')->index();
            $table->json('validation_errors')->nullable();
            $table->json('validation_warnings')->nullable();
            $table->timestamps();
            $table->unique(['batch_id', 'source_row']);
            $table->index(['batch_id', 'reg']);
            $table->index(['batch_id', 'user_id']);
        });

        Schema::connection('exam')->create('preliminary_results', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('registration_id')->unique();
            $table->string('user_id', 10)->unique();
            $table->string('reg', 8)->unique();
            $table->decimal('mark', 6, 2)->nullable()->index();
            $table->text('raw_candidate_status')->nullable();
            $table->string('candidate_status', 20)->default('active')->index();
            $table->string('result_status', 20)->nullable()->index();
            $table->decimal('applied_cutoff_mark', 6, 2)->nullable();
            $table->string('validation_status', 30)->default('pending')->index();
            $table->unsignedBigInteger('source_batch_id')->index();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();
        });

        Schema::connection('exam')->create('preliminary_processing_states', function (Blueprint $table): void {
            $table->id();
            $table->string('status', 40)->default('not_started');
            $table->unsignedBigInteger('latest_import_batch_id')->nullable();
            $table->decimal('cutoff_mark', 6, 2)->nullable();
            $table->unsignedBigInteger('cutoff_set_by')->nullable();
            $table->timestamp('cutoff_set_at')->nullable();
            $table->unsignedBigInteger('reconciliation_generated_by')->nullable();
            $table->timestamp('reconciliation_generated_at')->nullable();
            $table->unsignedBigInteger('result_finalized_by')->nullable();
            $table->timestamp('result_finalized_at')->nullable();
            $table->json('summary')->nullable();
            $table->timestamps();
        });

        Schema::connection('exam')->create('preliminary_processing_audits', function (Blueprint $table): void {
            $table->id();
            $table->string('action', 80)->index();
            $table->string('status_before', 40)->nullable();
            $table->string('status_after', 40)->nullable();
            $table->unsignedBigInteger('batch_id')->nullable()->index();
            $table->unsignedBigInteger('processing_run_id')->nullable()->index();
            $table->unsignedBigInteger('actor_id');
            $table->string('actor_name')->nullable();
            $table->text('reason')->nullable();
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
        foreach (['preliminary_processing_audits','preliminary_processing_states','preliminary_results','preliminary_import_staging','preliminary_import_batches'] as $table) {
            Schema::connection('exam')->dropIfExists($table);
        }
    }
};
