<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('exam');

        $schema->create('choice_validation_import_batches', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('examination_id')->index();
            $table->string('original_name');
            $table->string('stored_name');
            $table->string('status', 40)->default('queued')->index();
            $table->unsignedInteger('configured_maximum_choices');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('valid_rows')->default(0);
            $table->unsignedInteger('invalid_rows')->default(0);
            $table->unsignedInteger('approved_rows')->default(0);
            $table->decimal('progress_percent', 7, 4)->default(0);
            $table->text('failure_message')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('approved_by')->nullable()->index();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        $schema->create('choice_validation_import_staging', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('batch_id');
            $table->foreign('batch_id', 'cv_import_staging_batch_fk')
                ->references('id')->on('choice_validation_import_batches')
                ->cascadeOnDelete();
            $table->unsignedInteger('source_row');
            $table->json('raw_payload');
            $table->unsignedBigInteger('registration_id')->nullable()->index();
            $table->string('user_id', 64)->nullable()->index();
            $table->string('reg', 32)->nullable()->index();
            $table->json('raw_choices')->nullable();
            $table->unsignedInteger('raw_choice_count')->default(0);
            $table->string('validation_status', 24)->default('pending')->index();
            $table->json('validation_errors')->nullable();
            $table->json('validation_warnings')->nullable();
            $table->timestamps();
            $table->unique(['batch_id', 'source_row'], 'cv_import_staging_batch_row_uq');
        });

        $schema->create('choice_validation_sources', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('registration_id')->index();
            $table->string('user_id', 64)->nullable()->index();
            $table->string('reg', 32)->index();
            $table->unsignedInteger('source_version')->index();
            $table->unsignedBigInteger('source_batch_id')->nullable();
            $table->foreign('source_batch_id', 'cv_sources_batch_fk')
                ->references('id')->on('choice_validation_import_batches')
                ->nullOnDelete();
            $table->unsignedInteger('source_row')->nullable();
            $table->json('source_snapshot');
            $table->unsignedInteger('raw_choice_count');
            $table->unsignedBigInteger('approved_by')->nullable()->index();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->unique(['registration_id', 'source_version'], 'choice_source_registration_version_unique');
        });

        $schema->create('choice_validation_source_items', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('choice_validation_source_id');
            $table->foreign('choice_validation_source_id', 'cv_source_items_source_fk')
                ->references('id')->on('choice_validation_sources')
                ->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->string('source_column', 32);
            $table->text('raw_value')->nullable();
            $table->string('choice_code', 40)->nullable()->index();
            $table->timestamps();
            $table->unique(['choice_validation_source_id', 'position'], 'cv_source_items_source_pos_uq');
        });

        $schema->create('choice_validation_processing_states', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->string('status', 40)->default('not_started')->index();
            $table->unsignedInteger('current_source_version')->default(0);
            $table->unsignedInteger('approved_source_version')->nullable();
            $table->boolean('is_stale')->default(false)->index();
            $table->text('stale_reason')->nullable();
            $table->json('summary')->nullable();
            $table->timestamps();
        });

        $schema->create('choice_validation_processing_audits', function (Blueprint $table): void {
            $table->id();
            $table->string('action', 80)->index();
            $table->unsignedBigInteger('actor_id')->nullable()->index();
            $table->string('actor_name')->nullable();
            $table->text('reason')->nullable();
            $table->json('summary')->nullable();
            $table->unsignedBigInteger('batch_id')->nullable()->index();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        $schema = Schema::connection('exam');
        foreach ([
            'choice_validation_processing_audits',
            'choice_validation_processing_states',
            'choice_validation_source_items',
            'choice_validation_sources',
            'choice_validation_import_staging',
            'choice_validation_import_batches',
        ] as $table) {
            $schema->dropIfExists($table);
        }
    }
};
