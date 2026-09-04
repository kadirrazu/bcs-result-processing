<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('exam');

        $schema->create('allocation_a5_runs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('version')->unique('alloc_a5_run_ver_uq');
            $table->unsignedBigInteger('allocation_a4_run_id')->index();
            $table->string('status', 40)->default('queued')->index();
            $table->string('phase', 60)->default('QUEUED')->index();
            $table->string('a4_output_hash', 64)->index();
            $table->unsignedInteger('circular_version')->index();
            $table->string('circular_hash', 64)->index();
            $table->string('registration_hash', 64)->nullable()->index();
            $table->string('candidate_result_hash', 64)->nullable()->index();
            $table->string('capacity_result_hash', 64)->nullable()->index();
            $table->unsignedInteger('total_allocated')->default(0);
            $table->unsignedInteger('candidate_passed')->default(0);
            $table->unsignedInteger('candidate_failed')->default(0);
            $table->unsignedInteger('capacity_checked')->default(0);
            $table->unsignedInteger('capacity_failed')->default(0);
            $table->unsignedInteger('progress_percent')->default(0);
            $table->unsignedInteger('progress_current')->default(0);
            $table->unsignedInteger('progress_total')->default(0);
            $table->text('progress_message')->nullable();
            $table->text('failure_message')->nullable();
            $table->boolean('is_stale')->default(false)->index('alloc_a5_stale_idx');
            $table->text('stale_reason')->nullable();
            $table->timestamp('staled_at')->nullable()->index('alloc_a5_staled_at_idx');
            $table->unsignedBigInteger('started_by')->nullable()->index();
            $table->timestamp('started_at')->nullable()->index();
            $table->timestamp('completed_at')->nullable()->index();
            $table->unsignedBigInteger('finalized_by')->nullable()->index();
            $table->timestamp('finalized_at')->nullable()->index();
            $table->timestamps();

            $table->foreign('allocation_a4_run_id', 'alloc_a5_a4_fk')
                ->references('id')->on('allocation_a4_runs')->restrictOnDelete();
        });

        $schema->create('allocation_a5_candidate_results', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('allocation_a5_run_id')->index();
            $table->unsignedBigInteger('allocation_a4_result_id')->index();
            $table->unsignedBigInteger('registration_id')->index();
            $table->string('reg', 20)->index();
            $table->unsignedBigInteger('circular_entry_id')->index();
            $table->unsignedInteger('cadre_code')->index();
            $table->string('cadre_type', 2)->index();
            $table->string('allocation_basis', 10)->index();
            $table->string('bachelor_status', 20)->index();
            $table->string('prs_status', 20)->index();
            $table->string('technical_status', 20)->index();
            $table->string('quota_status', 20)->index();
            $table->string('overall_status', 20)->index();
            $table->json('reason_codes')->nullable();
            $table->string('candidate_bachelor_subject_code', 30)->nullable();
            $table->string('candidate_prs_code', 30)->nullable();
            $table->json('allowed_bachelor_subject_codes')->nullable();
            $table->json('allowed_prs_codes')->nullable();
            $table->json('registration_quota_snapshot')->nullable();
            $table->timestamps();
            $table->unique(['allocation_a5_run_id', 'registration_id'], 'alloc_a5_candidate_uq');
            $table->foreign('allocation_a5_run_id', 'alloc_a5_candidate_run_fk')
                ->references('id')->on('allocation_a5_runs')->cascadeOnDelete();
        });

        $schema->create('allocation_a5_capacity_results', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('allocation_a5_run_id')->index();
            $table->unsignedBigInteger('circular_entry_id')->index();
            $table->unsignedInteger('cadre_code')->index();
            $table->unsignedInteger('sanctioned_posts');
            $table->unsignedInteger('allocated_count');
            $table->integer('remaining_posts');
            $table->string('status', 20)->index();
            $table->string('reason_code', 80)->nullable()->index();
            $table->timestamps();
            $table->unique(['allocation_a5_run_id', 'circular_entry_id'], 'alloc_a5_capacity_uq');
            $table->foreign('allocation_a5_run_id', 'alloc_a5_capacity_run_fk')
                ->references('id')->on('allocation_a5_runs')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        $schema = Schema::connection('exam');
        $schema->dropIfExists('allocation_a5_capacity_results');
        $schema->dropIfExists('allocation_a5_candidate_results');
        $schema->dropIfExists('allocation_a5_runs');
    }
};
