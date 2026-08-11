<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('exam');

        $schema->create('choice_validation_finalization_runs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('source_version')->index();
            $table->unsignedInteger('validation_version')->index();
            $table->unsignedInteger('circular_version')->index();
            $table->unsignedBigInteger('validation_run_id')->index();

            $table->string('status', 24)->default('finalized')->index();
            $table->char('dataset_hash', 64)->index();

            $table->unsignedInteger('total_candidates')->default(0);
            $table->unsignedInteger('valid_candidates')->default(0);
            $table->unsignedInteger('not_applicable_candidates')->default(0);
            $table->unsignedInteger('zero_valid_choice_candidates')->default(0);
            $table->unsignedInteger('kept_choices')->default(0);
            $table->unsignedInteger('removed_choices')->default(0);
            $table->unsignedInteger('expanded_choices')->default(0);

            $table->unsignedBigInteger('finalized_by')->index();
            $table->string('finalized_by_name')->nullable();
            $table->text('finalization_note');
            $table->timestamp('finalized_at')->index();
            $table->timestamp('created_at')->useCurrent();

            $table->index(
                ['validation_version', 'status'],
                'cv_final_runs_val_status_idx'
            );
        });

        $schema->table('choice_validation_processing_states', function (Blueprint $table): void {
            $table->unsignedInteger('finalized_validation_version')
                ->nullable()
                ->after('validation_completed_at');
            $table->unsignedBigInteger('latest_finalization_run_id')
                ->nullable()
                ->after('finalized_validation_version');
            $table->timestamp('finalized_at')
                ->nullable()
                ->after('latest_finalization_run_id');
        });
    }

    public function down(): void
    {
        $schema = Schema::connection('exam');

        $schema->table('choice_validation_processing_states', function (Blueprint $table): void {
            $table->dropColumn([
                'finalized_validation_version',
                'latest_finalization_run_id',
                'finalized_at',
            ]);
        });

        $schema->dropIfExists('choice_validation_finalization_runs');
    }
};
