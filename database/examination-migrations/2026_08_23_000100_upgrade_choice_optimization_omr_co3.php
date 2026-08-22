<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('exam');

        if (! $schema->hasColumn('choice_optimization_omr_batches', 'review_rows')) {
            $schema->table('choice_optimization_omr_batches', function (Blueprint $table): void {
                $table->unsignedInteger('review_rows')->default(0)->after('conflict_rows');
            });
        }

        if (! $schema->hasColumn('choice_optimization_omr_batches', 'approved_rows')) {
            $schema->table('choice_optimization_omr_batches', function (Blueprint $table): void {
                $table->unsignedInteger('approved_rows')->default(0)->after('review_rows');
            });
        }

        if (! $schema->hasColumn('choice_optimization_omr_batches', 'approved_at')) {
            $schema->table('choice_optimization_omr_batches', function (Blueprint $table): void {
                $table->timestamp('approved_at')->nullable()->after('validated_at');
            });
        }

        if (! $schema->hasColumn('choice_optimization_omr_staging', 'effective_change_choice')) {
            $schema->table('choice_optimization_omr_staging', function (Blueprint $table): void {
                $table->string('effective_change_choice', 8)->nullable()->after('change_choice');
                $table->index('effective_change_choice', 'co_omr_stg_eff_change_idx');
            });
        }

        if (! $schema->hasColumn('choice_optimization_omr_staging', 'decision_resolution')) {
            $schema->table('choice_optimization_omr_staging', function (Blueprint $table): void {
                $table->string('decision_resolution', 48)->nullable()->after('effective_change_choice');
                $table->index('decision_resolution', 'co_omr_stg_decision_idx');
            });
        }

        if (! $schema->hasColumn('choice_optimization_omr_staging', 'decision_resolution_reason')) {
            $schema->table('choice_optimization_omr_staging', function (Blueprint $table): void {
                $table->text('decision_resolution_reason')->nullable()->after('decision_resolution');
            });
        }

        if (! $schema->hasColumn('choice_optimization_omr_staging', 'decision_resolved_by')) {
            $schema->table('choice_optimization_omr_staging', function (Blueprint $table): void {
                $table->unsignedBigInteger('decision_resolved_by')->nullable()->after('decision_resolution_reason');
                $table->index('decision_resolved_by', 'co_omr_stg_decided_by_idx');
            });
        }

        if (! $schema->hasColumn('choice_optimization_omr_staging', 'decision_resolved_at')) {
            $schema->table('choice_optimization_omr_staging', function (Blueprint $table): void {
                $table->timestamp('decision_resolved_at')->nullable()->after('decision_resolved_by');
            });
        }

        if (! $schema->hasColumn('choice_optimization_omr_staging', 'choice_validation_status')) {
            $schema->table('choice_optimization_omr_staging', function (Blueprint $table): void {
                $table->string('choice_validation_status', 32)->default('not_started')->after('written_qualified_track');
                $table->index('choice_validation_status', 'co_omr_stg_choice_status_idx');
            });
        }

        if (! $schema->hasColumn('choice_optimization_omr_staging', 'validated_omr_choice_codes')) {
            $schema->table('choice_optimization_omr_staging', function (Blueprint $table): void {
                $table->json('validated_omr_choice_codes')->nullable()->after('choice_validation_status');
            });
        }

        if (! $schema->hasColumn('choice_optimization_omr_staging', 'choice_validation_details')) {
            $schema->table('choice_optimization_omr_staging', function (Blueprint $table): void {
                $table->json('choice_validation_details')->nullable()->after('validated_omr_choice_codes');
            });
        }

        // Recovery for a previous failed attempt of this same, not-yet-recorded migration.
        // This table is introduced by this migration, so a pre-existing copy here can only
        // be the partial table left by that failed attempt and contains no authoritative data.
        $schema->dropIfExists('choice_optimization_effective_choices');

        $schema->create('choice_optimization_effective_choices', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('registration_id');
            $table->string('reg', 32);
            $table->unsignedBigInteger('choice_validation_result_id')->nullable();
            $table->unsignedBigInteger('omr_staging_id')->nullable();
            $table->string('choice_source', 40);
            $table->json('validated_choice_codes');
            $table->json('omr_override_choice_codes')->nullable();
            $table->json('effective_choice_codes');
            $table->string('change_reason_code', 80);
            $table->text('change_reason_text')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            // Keep every MySQL identifier comfortably below the 64-character limit.
            $table->index('registration_id', 'co_eff_registration_idx');
            $table->index('reg', 'co_eff_reg_idx');
            $table->index('choice_validation_result_id', 'co_eff_cv_result_idx');
            $table->index('omr_staging_id', 'co_eff_omr_staging_idx');
            $table->index('choice_source', 'co_eff_source_idx');
            $table->index('change_reason_code', 'co_eff_reason_idx');
            $table->index('approved_by', 'co_eff_approved_by_idx');
            $table->index('approved_at', 'co_eff_approved_at_idx');
            $table->unique('registration_id', 'co_eff_registration_uq');
        });
    }

    public function down(): void
    {
        $schema = Schema::connection('exam');
        $schema->dropIfExists('choice_optimization_effective_choices');

        $stagingColumns = [
            'effective_change_choice',
            'decision_resolution',
            'decision_resolution_reason',
            'decision_resolved_by',
            'decision_resolved_at',
            'choice_validation_status',
            'validated_omr_choice_codes',
            'choice_validation_details',
        ];

        foreach ($stagingColumns as $column) {
            if ($schema->hasColumn('choice_optimization_omr_staging', $column)) {
                $schema->table('choice_optimization_omr_staging', function (Blueprint $table) use ($column): void {
                    $table->dropColumn($column);
                });
            }
        }

        foreach (['review_rows', 'approved_rows', 'approved_at'] as $column) {
            if ($schema->hasColumn('choice_optimization_omr_batches', $column)) {
                $schema->table('choice_optimization_omr_batches', function (Blueprint $table) use ($column): void {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
