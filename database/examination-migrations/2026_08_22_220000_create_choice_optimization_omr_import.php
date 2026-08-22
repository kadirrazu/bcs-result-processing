<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('exam');

        $schema->create('choice_optimization_omr_batches', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('examination_id')->index();
            $table->string('original_name');
            $table->string('stored_name');
            $table->string('status', 40)->default('queued')->index();
            $table->unsignedInteger('configured_maximum_choices')->default(20);
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('valid_rows')->default(0);
            $table->unsignedInteger('invalid_rows')->default(0);
            $table->unsignedInteger('conflict_rows')->default(0);
            $table->decimal('progress_percent', 7, 4)->default(0);
            $table->text('failure_message')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        $schema->create('choice_optimization_omr_staging', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('batch_id');
            $table->foreign('batch_id', 'co_omr_stage_batch_fk')
                ->references('id')->on('choice_optimization_omr_batches')
                ->cascadeOnDelete();
            $table->unsignedInteger('source_row');
            $table->json('raw_payload');
            $table->string('raw_reg', 32)->nullable()->index();
            $table->string('effective_reg', 32)->nullable()->index();
            $table->string('change_choice', 8)->nullable()->index();
            $table->json('raw_choices')->nullable();
            $table->unsignedInteger('raw_choice_count')->default(0);
            $table->unsignedBigInteger('registration_id')->nullable()->index();
            $table->string('written_qualified_track', 8)->nullable()->index();
            $table->string('validation_status', 24)->default('pending')->index();
            $table->json('validation_errors')->nullable();
            $table->json('validation_warnings')->nullable();
            $table->string('resolution_status', 24)->default('unresolved')->index();
            $table->text('resolution_reason')->nullable();
            $table->unsignedBigInteger('resolved_by')->nullable()->index();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->unique(['batch_id', 'source_row'], 'co_omr_stage_batch_row_uq');
            $table->index(['batch_id', 'effective_reg'], 'co_omr_stage_batch_reg_idx');
        });
    }

    public function down(): void
    {
        $schema = Schema::connection('exam');
        $schema->dropIfExists('choice_optimization_omr_staging');
        $schema->dropIfExists('choice_optimization_omr_batches');
    }
};
