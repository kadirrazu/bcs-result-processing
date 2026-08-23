<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('previous_bcs_repositories', function (Blueprint $table): void {
            $table->id();
            $table->unsignedSmallInteger('bcs_number')->unique('prev_bcs_repo_bcs_uq');
            $table->unsignedBigInteger('current_effective_dataset_id')->nullable()->index('prev_bcs_repo_effective_idx');
            $table->timestamps();
        });

        Schema::create('previous_bcs_repository_datasets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('repository_id')->constrained('previous_bcs_repositories')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('original_name');
            $table->string('stored_name');
            $table->string('status', 40)->default('queued')->index('prev_bcs_ds_status_idx');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('staged_rows')->default(0);
            $table->unsignedInteger('valid_rows')->default(0);
            $table->unsignedInteger('invalid_rows')->default(0);
            $table->decimal('progress_percent', 7, 4)->default(0);
            $table->text('failure_message')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('staged_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['repository_id', 'version'], 'prev_bcs_ds_repo_ver_uq');
        });

        Schema::create('previous_bcs_repository_rows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('dataset_id')->constrained('previous_bcs_repository_datasets')->cascadeOnDelete();
            $table->unsignedInteger('source_row');
            $table->json('raw_payload');

            $table->string('reg', 40)->nullable();
            $table->string('name')->nullable();
            $table->string('fname')->nullable();
            $table->string('mname')->nullable();

            $table->string('b_date_raw', 50)->nullable();
            $table->date('b_date')->nullable();
            $table->string('dob_raw', 100)->nullable();
            $table->date('dob')->nullable();

            $table->string('dist_name')->nullable();
            $table->string('ssc_roll', 80)->nullable();
            $table->unsignedSmallInteger('ssc_year')->nullable();
            $table->string('hsc_roll', 80)->nullable();
            $table->unsignedSmallInteger('hsc_year')->nullable();
            $table->string('nid_no', 80)->nullable();
            $table->string('cadre', 80)->nullable();

            $table->string('validation_status', 20)->default('pending');
            $table->json('validation_errors')->nullable();
            $table->timestamps();

            $table->unique(['dataset_id', 'source_row'], 'prev_bcs_row_ds_source_uq');
            $table->index(['dataset_id', 'validation_status'], 'prev_bcs_row_ds_status_idx');
            $table->index(['ssc_roll', 'ssc_year', 'b_date'], 'prev_bcs_row_ssc_dob_idx');
            $table->index(['hsc_roll', 'hsc_year'], 'prev_bcs_row_hsc_idx');
            $table->index('nid_no', 'prev_bcs_row_nid_idx');
        });

        Schema::create('previous_bcs_repository_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('repository_id')->nullable()->constrained('previous_bcs_repositories')->nullOnDelete();
            $table->foreignId('dataset_id')->nullable()->constrained('previous_bcs_repository_datasets')->nullOnDelete();
            $table->string('action', 80);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('context')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['repository_id', 'created_at'], 'prev_bcs_audit_repo_time_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('previous_bcs_repository_audits');
        Schema::dropIfExists('previous_bcs_repository_rows');
        Schema::dropIfExists('previous_bcs_repository_datasets');
        Schema::dropIfExists('previous_bcs_repositories');
    }
};
