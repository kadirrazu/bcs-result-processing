<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('exam');

        $schema->create('merit_processing_states', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->string('status', 40)->default('not_started')->index();
            $table->unsignedBigInteger('latest_run_id')->nullable()->index();
            $table->unsignedBigInteger('latest_finalization_run_id')->nullable()->index();
            $table->boolean('is_stale')->default(false)->index();
            $table->text('stale_reason')->nullable();
            $table->json('source_snapshot')->nullable();
            $table->string('dataset_hash', 64)->nullable()->index();
            $table->json('summary')->nullable();
            $table->unsignedBigInteger('finalized_by')->nullable()->index();
            $table->timestamp('finalized_at')->nullable()->index();
            $table->timestamps();
        });

        $schema->create('merit_processing_runs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('processing_version')->unique('merit_proc_ver_uq');
            $table->string('status', 30)->default('queued')->index();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('common_ranked_count')->default(0);
            $table->unsignedInteger('general_ranked_count')->default(0);
            $table->unsignedInteger('technical_ranked_count')->default(0);
            $table->unsignedInteger('cadre_rank_rows')->default(0);
            $table->decimal('progress_percent', 8, 4)->default(0);
            $table->string('current_step', 180)->nullable();
            $table->json('source_snapshot');
            $table->string('dataset_hash', 64)->nullable()->index();
            $table->json('summary')->nullable();
            $table->longText('failure_message')->nullable();
            $table->unsignedBigInteger('created_by')->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        $schema->create('merit_results', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('processing_run_id')->index();
            $table->unsignedInteger('processing_version')->index();
            $table->unsignedBigInteger('tabulation_result_id')->index();
            $table->unsignedBigInteger('registration_id')->index();
            $table->string('user_id', 20)->index();
            $table->string('reg', 20)->index();
            $table->unsignedTinyInteger('cadre_category')->nullable()->index();
            $table->string('written_qualified_track', 10)->nullable()->index();
            $table->unsignedSmallInteger('graduation_year')->nullable()->index();
            $table->unsignedInteger('common_merit_position')->nullable()->index();
            $table->unsignedInteger('general_merit_position')->nullable()->index();
            $table->unsignedInteger('technical_merit_position')->nullable()->index();
            $table->json('all_merit_tech')->nullable();
            $table->boolean('common_merit_eligible')->default(false)->index();
            $table->boolean('general_merit_eligible')->default(false)->index();
            $table->boolean('technical_merit_eligible')->default(false)->index();
            $table->string('status_reason', 80)->nullable()->index();
            $table->timestamp('processed_at')->index();
            $table->timestamps();
            $table->unique(['processing_run_id','registration_id'], 'merit_run_reg_uq');
            $table->unique(['processing_run_id','common_merit_position'], 'merit_run_common_uq');
            $table->unique(['processing_run_id','general_merit_position'], 'merit_run_general_uq');
            $table->unique(['processing_run_id','technical_merit_position'], 'merit_run_tech_uq');
            $table->foreign('processing_run_id','merit_result_run_fk')->references('id')->on('merit_processing_runs')->cascadeOnDelete();
        });

        $schema->create('merit_cadre_ranks', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('processing_run_id')->index();
            $table->unsignedInteger('processing_version')->index();
            $table->unsignedBigInteger('merit_result_id')->index();
            $table->unsignedBigInteger('registration_id')->index();
            $table->unsignedBigInteger('circular_entry_id')->nullable()->index();
            $table->unsignedInteger('cadre_code')->index();
            $table->string('cadre_abbr', 20)->index();
            $table->string('cadre_type', 2)->index();
            $table->unsignedInteger('cadre_merit_position')->index();
            $table->unsignedInteger('source_merit_position')->index();
            $table->unsignedInteger('choice_position')->index();
            $table->string('qualification_basis', 80)->default('VALIDATED_CHOICE_AND_CIRCULAR_ELIGIBILITY');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['processing_run_id','cadre_code','registration_id'], 'merit_cadre_reg_uq');
            $table->unique(['processing_run_id','cadre_code','cadre_merit_position'], 'merit_cadre_rank_uq');
            $table->index(['processing_run_id','cadre_code','source_merit_position'], 'merit_cadre_source_idx');
            $table->foreign('processing_run_id','merit_cadre_run_fk')->references('id')->on('merit_processing_runs')->cascadeOnDelete();
            $table->foreign('merit_result_id','merit_cadre_result_fk')->references('id')->on('merit_results')->cascadeOnDelete();
        });

        $schema->create('merit_finalization_runs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('processing_run_id')->index();
            $table->unsignedInteger('processing_version')->index();
            $table->string('status', 30)->default('current')->index();
            $table->json('source_snapshot');
            $table->string('dataset_hash', 64)->index();
            $table->json('summary');
            $table->unsignedBigInteger('finalized_by')->index();
            $table->timestamp('finalized_at')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->foreign('processing_run_id','merit_final_run_fk')->references('id')->on('merit_processing_runs')->restrictOnDelete();
        });

        $schema->create('merit_processing_audits', function (Blueprint $table): void {
            $table->id();
            $table->string('event', 90)->index();
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
        $schema->dropIfExists('merit_processing_audits');
        $schema->dropIfExists('merit_finalization_runs');
        $schema->dropIfExists('merit_cadre_ranks');
        $schema->dropIfExists('merit_results');
        $schema->dropIfExists('merit_processing_runs');
        $schema->dropIfExists('merit_processing_states');
    }
};
