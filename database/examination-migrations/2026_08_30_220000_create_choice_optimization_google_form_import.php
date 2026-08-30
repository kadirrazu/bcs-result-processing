<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('exam');

        // This migration is intentionally retry-safe. MySQL may keep DDL from a
        // failed migration even though Laravel never records the migration as run.
        // Add setting columns only when they are genuinely missing.
        if (! $schema->hasColumn('choice_optimization_settings', 'google_form_enabled')) {
            $schema->table('choice_optimization_settings', function (Blueprint $table): void {
                $table->boolean('google_form_enabled')
                    ->nullable()
                    ->after('optimization_enabled')
                    ->index('co_settings_gf_enabled_idx');
            });
        }

        if (! $schema->hasColumn('choice_optimization_settings', 'google_form_decided_by')) {
            $schema->table('choice_optimization_settings', function (Blueprint $table): void {
                $table->unsignedBigInteger('google_form_decided_by')
                    ->nullable()
                    ->after('google_form_enabled')
                    ->index('co_settings_gf_by_idx');
            });
        }

        if (! $schema->hasColumn('choice_optimization_settings', 'google_form_decided_at')) {
            $schema->table('choice_optimization_settings', function (Blueprint $table): void {
                $table->timestamp('google_form_decided_at')
                    ->nullable()
                    ->after('google_form_decided_by')
                    ->index('co_settings_gf_at_idx');
            });
        }

        // These three tables belong exclusively to this not-yet-completed migration.
        // If an earlier attempt failed after CREATE TABLE but before all indexes/FKs
        // were added, discard only those incomplete new tables and recreate them.
        // No accepted Google Form data can legitimately exist before this migration
        // is successfully recorded by Laravel.
        $schema->dropIfExists('choice_optimization_google_form_recommendations');
        $schema->dropIfExists('choice_optimization_google_form_rows');
        $schema->dropIfExists('choice_optimization_google_form_batches');

        $schema->create('choice_optimization_google_form_batches', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('examination_id')->index('co_gf_batches_exam_idx');
            $table->string('original_name');
            $table->string('stored_name');
            $table->string('status', 40)->default('queued')->index('co_gf_batches_status_idx');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('valid_rows')->default(0);
            $table->unsignedInteger('invalid_rows')->default(0);
            $table->unsignedInteger('merged_rows')->default(0);
            $table->decimal('progress_percent', 5, 2)->default(0);
            $table->text('failure_message')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index('co_gf_batches_created_by_idx');
            $table->unsignedBigInteger('validated_by')->nullable()->index('co_gf_batches_valid_by_idx');
            $table->unsignedBigInteger('merged_by')->nullable()->index('co_gf_batches_merged_by_idx');
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->timestamp('merged_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        $schema->create('choice_optimization_google_form_rows', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('batch_id');
            $table->unsignedInteger('source_row');
            $table->json('raw_payload')->nullable();
            $table->string('raw_reg', 50)->nullable()->index('co_gf_rows_raw_reg_idx');
            $table->string('raw_bcs', 20)->nullable();
            $table->string('raw_cadre', 50)->nullable();
            $table->unsignedBigInteger('registration_id')->nullable()->index('co_gf_rows_reg_id_idx');
            $table->string('current_reg', 50)->nullable()->index('co_gf_rows_current_reg_idx');
            $table->unsignedSmallInteger('previous_bcs_number')->nullable()->index('co_gf_rows_prev_bcs_idx');
            $table->string('cadre', 50)->nullable()->index('co_gf_rows_cadre_idx');
            $table->string('validation_status', 20)->default('pending')->index('co_gf_rows_valid_status_idx');
            $table->json('validation_errors')->nullable();
            $table->json('validation_warnings')->nullable();
            $table->string('merge_status', 20)->default('pending')->index('co_gf_rows_merge_status_idx');
            $table->unsignedBigInteger('merged_recommendation_id')->nullable()->index('co_gf_rows_merged_rec_idx');
            $table->timestamp('merged_at')->nullable();
            $table->timestamps();

            $table->unique(['batch_id', 'source_row'], 'co_gf_rows_batch_source_uq');
            $table->foreign('batch_id', 'co_gf_rows_batch_fk')
                ->references('id')->on('choice_optimization_google_form_batches')->cascadeOnDelete();
        });

        $schema->create('choice_optimization_google_form_recommendations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('registration_id')->index('co_gf_recs_reg_id_idx');
            $table->string('current_reg', 50)->index('co_gf_recs_current_reg_idx');
            $table->unsignedSmallInteger('previous_bcs_number')->index('co_gf_recs_prev_bcs_idx');
            $table->string('cadre', 50)->index('co_gf_recs_cadre_idx');
            $table->unsignedBigInteger('source_batch_id')->index('co_gf_recs_batch_id_idx');
            $table->unsignedBigInteger('source_row_id')->index('co_gf_recs_row_id_idx');
            $table->unsignedBigInteger('accepted_by')->nullable()->index('co_gf_recs_accepted_by_idx');
            $table->timestamp('accepted_at')->nullable()->index('co_gf_recs_accepted_at_idx');
            $table->timestamps();

            $table->unique(['registration_id', 'previous_bcs_number'], 'co_gf_recs_reg_bcs_uq');
            $table->foreign('source_batch_id', 'co_gf_recs_batch_fk')
                ->references('id')->on('choice_optimization_google_form_batches')->cascadeOnDelete();
            $table->foreign('source_row_id', 'co_gf_recs_row_fk')
                ->references('id')->on('choice_optimization_google_form_rows')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        $schema = Schema::connection('exam');

        $schema->dropIfExists('choice_optimization_google_form_recommendations');
        $schema->dropIfExists('choice_optimization_google_form_rows');
        $schema->dropIfExists('choice_optimization_google_form_batches');

        $columns = array_values(array_filter([
            $schema->hasColumn('choice_optimization_settings', 'google_form_enabled') ? 'google_form_enabled' : null,
            $schema->hasColumn('choice_optimization_settings', 'google_form_decided_by') ? 'google_form_decided_by' : null,
            $schema->hasColumn('choice_optimization_settings', 'google_form_decided_at') ? 'google_form_decided_at' : null,
        ]));

        if ($columns !== []) {
            $schema->table('choice_optimization_settings', function (Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
        }
    }
};
