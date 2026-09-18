<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('exam');

        $schema->create('non_cadre_processing_states', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->string('status', 30)->default('not_started')->index();
            $table->string('circular_status', 30)->default('not_started')->index();
            $table->string('seat_breakup_status', 30)->default('not_started')->index();
            $table->string('choice_status', 30)->default('not_started')->index();
            $table->string('allocation_status', 30)->default('not_started')->index();
            $table->string('reporting_status', 30)->default('not_started')->index();
            $table->boolean('is_stale')->default(false)->index();
            $table->text('stale_reason')->nullable();
            $table->timestamp('staled_at')->nullable()->index();
            $table->unsignedBigInteger('cadre_allocation_a5_run_id')->nullable()->index();
            $table->string('cadre_allocation_candidate_hash', 64)->nullable();
            $table->timestamps();
        });

        $schema->create('non_cadre_circular_versions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('version')->unique();
            $table->string('status', 30)->default('draft')->index();
            $table->string('source_filename')->nullable();
            $table->string('source_hash', 64)->nullable()->index();
            $table->string('dataset_hash', 64)->nullable()->index();
            $table->unsignedInteger('total_posts')->default(0);
            $table->unsignedInteger('total_seats')->default(0);
            $table->unsignedInteger('valid_rows')->default(0);
            $table->unsignedInteger('invalid_rows')->default(0);
            $table->boolean('is_stale')->default(false)->index();
            $table->text('stale_reason')->nullable();
            $table->timestamp('staled_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('finalized_by')->nullable()->index();
            $table->timestamp('finalized_at')->nullable()->index();
            $table->timestamps();
        });

        $schema->create('non_cadre_circular_posts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('circular_version_id')->index();
            $table->unsignedSmallInteger('post_grade')->nullable()->index();
            $table->unsignedInteger('post_serial')->index();
            $table->unsignedInteger('post_sub_serial')->nullable()->index();
            $table->string('ministry');
            $table->string('ministry_bn')->nullable();
            $table->string('entity');
            $table->string('entity_bn')->nullable();
            $table->string('post_title');
            $table->string('post_title_bn')->nullable();
            $table->string('post_code', 50)->index();
            $table->unsignedInteger('post_count');
            $table->text('bachelor_subject_codes')->nullable();
            $table->string('status', 20)->default('ACTIVE')->index();
            $table->boolean('special_requirement')->default(false)->index();
            $table->text('special_requirement_note')->nullable();
            $table->timestamps();
            $table->unique(['circular_version_id', 'post_code'], 'nc_circ_post_version_code_uq');
            $table->foreign('circular_version_id', 'nc_circ_post_version_fk')->references('id')->on('non_cadre_circular_versions')->cascadeOnDelete();
        });

        $schema->create('non_cadre_seat_breakup_versions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('version')->unique();
            $table->unsignedBigInteger('circular_version_id')->index();
            $table->string('status', 30)->default('draft')->index();
            $table->string('source_filename')->nullable();
            $table->string('source_hash', 64)->nullable();
            $table->string('dataset_hash', 64)->nullable()->index();
            $table->boolean('is_stale')->default(false)->index();
            $table->text('stale_reason')->nullable();
            $table->timestamp('staled_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('finalized_by')->nullable();
            $table->timestamp('finalized_at')->nullable()->index();
            $table->timestamps();
            $table->foreign('circular_version_id', 'nc_seat_version_circ_fk')->references('id')->on('non_cadre_circular_versions')->restrictOnDelete();
        });

        $schema->create('non_cadre_seat_breakup_rows', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('seat_breakup_version_id')->index();
            $table->unsignedBigInteger('circular_post_id')->index();
            $table->string('post_code', 50)->index();
            $table->unsignedInteger('total_post');
            $table->unsignedInteger('mq_post')->default(0);
            $table->unsignedInteger('cff_post')->default(0);
            $table->unsignedInteger('em_post')->default(0);
            $table->unsignedInteger('phc_post')->default(0);
            $table->timestamps();
            $table->unique(['seat_breakup_version_id', 'post_code'], 'nc_seat_version_post_uq');
            $table->foreign('seat_breakup_version_id', 'nc_seat_row_version_fk')->references('id')->on('non_cadre_seat_breakup_versions')->cascadeOnDelete();
            $table->foreign('circular_post_id', 'nc_seat_row_post_fk')->references('id')->on('non_cadre_circular_posts')->restrictOnDelete();
        });

        $schema->create('non_cadre_choice_imports', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('version')->unique();
            $table->unsignedBigInteger('circular_version_id')->index();
            $table->string('status', 30)->default('uploaded')->index();
            $table->string('source_filename');
            $table->string('source_hash', 64)->nullable()->index();
            $table->unsignedSmallInteger('max_choices')->default(20);
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('valid_rows')->default(0);
            $table->unsignedInteger('invalid_rows')->default(0);
            $table->string('dataset_hash', 64)->nullable()->index();
            $table->boolean('is_stale')->default(false)->index();
            $table->text('stale_reason')->nullable();
            $table->timestamp('staled_at')->nullable();
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->unsignedBigInteger('finalized_by')->nullable();
            $table->timestamp('finalized_at')->nullable()->index();
            $table->timestamps();
            $table->foreign('circular_version_id', 'nc_choice_import_circ_fk')->references('id')->on('non_cadre_circular_versions')->restrictOnDelete();
        });

        $schema->create('non_cadre_choice_items', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('choice_import_id')->index();
            $table->unsignedBigInteger('registration_id')->nullable()->index();
            $table->string('user_id', 30)->index();
            $table->string('reg', 20)->index();
            $table->json('original_choices');
            $table->json('validated_choices')->nullable();
            $table->json('effective_choices')->nullable();
            $table->string('validation_status', 30)->default('pending')->index();
            $table->json('validation_summary')->nullable();
            $table->timestamps();
            $table->unique(['choice_import_id', 'reg'], 'nc_choice_import_reg_uq');
            $table->foreign('choice_import_id', 'nc_choice_item_import_fk')->references('id')->on('non_cadre_choice_imports')->cascadeOnDelete();
        });

        $schema->create('non_cadre_choice_rejections', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('choice_item_id')->index();
            $table->unsignedSmallInteger('choice_position');
            $table->string('post_code', 50)->nullable()->index();
            $table->string('reason_code', 80)->index();
            $table->text('reason');
            $table->timestamp('created_at')->useCurrent();
            $table->foreign('choice_item_id', 'nc_choice_reject_item_fk')->references('id')->on('non_cadre_choice_items')->cascadeOnDelete();
        });

        $schema->create('non_cadre_choice_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('choice_item_id')->index();
            $table->json('before_choices');
            $table->json('after_choices');
            $table->text('reason');
            $table->unsignedBigInteger('actor_id')->nullable()->index();
            $table->timestamp('created_at')->useCurrent()->index();
            $table->foreign('choice_item_id', 'nc_choice_adjust_item_fk')->references('id')->on('non_cadre_choice_items')->cascadeOnDelete();
        });

        $schema->create('non_cadre_allocation_runs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('version')->unique();
            $table->unsignedBigInteger('cadre_allocation_a5_run_id')->index();
            $table->string('cadre_allocation_candidate_hash', 64);
            $table->unsignedBigInteger('circular_version_id')->index();
            $table->unsignedBigInteger('seat_breakup_version_id')->index();
            $table->unsignedBigInteger('choice_import_id')->index();
            $table->string('status', 30)->default('queued')->index();
            $table->string('input_hash', 64)->nullable()->index();
            $table->string('result_hash', 64)->nullable()->index();
            $table->unsignedInteger('total_candidates')->default(0);
            $table->unsignedInteger('total_allocated')->default(0);
            $table->unsignedInteger('total_unallocated')->default(0);
            $table->unsignedInteger('pending_special_reviews')->default(0);
            $table->boolean('is_stale')->default(false)->index();
            $table->text('stale_reason')->nullable();
            $table->timestamp('staled_at')->nullable();
            $table->unsignedBigInteger('started_by')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->unsignedBigInteger('finalized_by')->nullable();
            $table->timestamp('finalized_at')->nullable()->index();
            $table->timestamps();
            $table->foreign('circular_version_id', 'nc_alloc_run_circ_fk')->references('id')->on('non_cadre_circular_versions')->restrictOnDelete();
            $table->foreign('seat_breakup_version_id', 'nc_alloc_run_seat_fk')->references('id')->on('non_cadre_seat_breakup_versions')->restrictOnDelete();
            $table->foreign('choice_import_id', 'nc_alloc_run_choice_fk')->references('id')->on('non_cadre_choice_imports')->restrictOnDelete();
        });

        $schema->create('non_cadre_allocation_results', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('allocation_run_id')->index();
            $table->unsignedBigInteger('registration_id')->index();
            $table->string('reg', 20)->index();
            $table->unsignedInteger('common_merit_position')->index();
            $table->unsignedBigInteger('circular_post_id')->nullable()->index();
            $table->string('post_code', 50)->nullable()->index();
            $table->unsignedSmallInteger('choice_position')->nullable();
            $table->string('allocation_basis', 10)->nullable()->index();
            $table->string('decision_status', 30)->default('UNALLOCATED')->index();
            $table->text('decision_reason')->nullable();
            $table->boolean('requires_special_review')->default(false)->index();
            $table->timestamps();
            $table->unique(['allocation_run_id', 'registration_id'], 'nc_alloc_result_candidate_uq');
            $table->foreign('allocation_run_id', 'nc_alloc_result_run_fk')->references('id')->on('non_cadre_allocation_runs')->cascadeOnDelete();
            $table->foreign('circular_post_id', 'nc_alloc_result_post_fk')->references('id')->on('non_cadre_circular_posts')->restrictOnDelete();
        });

        $schema->create('non_cadre_special_requirement_reviews', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('allocation_run_id')->index();
            $table->unsignedBigInteger('registration_id')->index();
            $table->string('reg', 20)->index();
            $table->unsignedBigInteger('circular_post_id')->index();
            $table->string('post_code', 50)->index();
            $table->string('decision', 20)->default('PENDING')->index();
            $table->text('reason')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable()->index();
            $table->timestamp('reviewed_at')->nullable()->index();
            $table->timestamps();
            $table->unique(['allocation_run_id', 'registration_id', 'circular_post_id'], 'nc_special_review_uq');
            $table->foreign('allocation_run_id', 'nc_special_review_run_fk')->references('id')->on('non_cadre_allocation_runs')->cascadeOnDelete();
            $table->foreign('circular_post_id', 'nc_special_review_post_fk')->references('id')->on('non_cadre_circular_posts')->restrictOnDelete();
        });

        $schema->create('non_cadre_result_dispositions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('allocation_run_id')->index();
            $table->unsignedBigInteger('registration_id')->index();
            $table->string('reg', 20)->index();
            $table->string('post_code', 50)->index();
            $table->string('status', 20)->default('ACTIVE')->index();
            $table->text('reason')->nullable();
            $table->boolean('reallocate_cancelled_seat')->default(false);
            $table->unsignedBigInteger('changed_by')->nullable()->index();
            $table->timestamp('changed_at')->nullable()->index();
            $table->timestamps();
            $table->unique(['allocation_run_id', 'registration_id'], 'nc_disposition_candidate_uq');
            $table->foreign('allocation_run_id', 'nc_disposition_run_fk')->references('id')->on('non_cadre_allocation_runs')->cascadeOnDelete();
        });

        $schema->create('non_cadre_processing_audits', function (Blueprint $table): void {
            $table->id();
            $table->string('stage', 30)->index();
            $table->string('action', 80)->index();
            $table->string('entity_type', 80)->nullable()->index();
            $table->unsignedBigInteger('entity_id')->nullable()->index();
            $table->json('before_payload')->nullable();
            $table->json('after_payload')->nullable();
            $table->text('reason')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable()->index();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        DB::connection('exam')->table('non_cadre_processing_states')->insert([
            'id' => 1,
            'status' => 'not_started',
            'circular_status' => 'not_started',
            'seat_breakup_status' => 'not_started',
            'choice_status' => 'not_started',
            'allocation_status' => 'not_started',
            'reporting_status' => 'not_started',
            'is_stale' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        $schema = Schema::connection('exam');
        foreach ([
            'non_cadre_processing_audits',
            'non_cadre_result_dispositions',
            'non_cadre_special_requirement_reviews',
            'non_cadre_allocation_results',
            'non_cadre_allocation_runs',
            'non_cadre_choice_adjustments',
            'non_cadre_choice_rejections',
            'non_cadre_choice_items',
            'non_cadre_choice_imports',
            'non_cadre_seat_breakup_rows',
            'non_cadre_seat_breakup_versions',
            'non_cadre_circular_posts',
            'non_cadre_circular_versions',
            'non_cadre_processing_states',
        ] as $table) {
            $schema->dropIfExists($table);
        }
    }
};
