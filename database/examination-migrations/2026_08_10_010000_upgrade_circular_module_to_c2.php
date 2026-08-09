<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('exam');

        if ($schema->hasTable('circular_entries')) {
            $schema->table('circular_entries', function (Blueprint $table): void {
                // The real BCS circular may repeat the same effective code for multiple subject-wise rows.
                $table->dropUnique('circular_entries_effective_code_unique');
                $table->string('cadre_name_snapshot')->nullable()->after('cadre_type');
                $table->string('cadre_name_bn_snapshot')->nullable()->after('cadre_name_snapshot');
                $table->string('post_name_snapshot')->nullable()->after('cadre_name_bn_snapshot');
                $table->string('post_name_bn_snapshot')->nullable()->after('post_name_snapshot');
                $table->string('source', 20)->default('ui')->after('note');
                $table->index(['version', 'cadre_serial', 'sub_serial'], 'circular_entries_version_order_idx');
                $table->index(['version', 'effective_code'], 'circular_entries_version_code_idx');
            });
        }

        if (! $schema->hasTable('circular_import_batches')) {
            $schema->create('circular_import_batches', function (Blueprint $table): void {
                $table->id();
                $table->string('original_filename');
                $table->string('stored_path');
                $table->string('status', 30)->default('staged')->index();
                $table->unsignedInteger('total_rows')->default(0);
                $table->unsignedInteger('valid_rows')->default(0);
                $table->unsignedInteger('invalid_rows')->default(0);
                $table->unsignedBigInteger('uploaded_by')->nullable()->index();
                $table->unsignedBigInteger('approved_by')->nullable()->index();
                $table->timestamp('approved_at')->nullable();
                $table->unsignedInteger('approved_version')->nullable()->index();
                $table->text('approval_note')->nullable();
                $table->timestamps();
            });
        }

        if (! $schema->hasTable('circular_import_staging')) {
            $schema->create('circular_import_staging', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('batch_id')->constrained('circular_import_batches')->cascadeOnDelete();
                $table->unsignedInteger('row_number');
                $table->json('raw_data');
                $table->json('normalized_data')->nullable();
                $table->string('validation_status', 20)->default('pending')->index();
                $table->json('validation_errors')->nullable();
                $table->timestamps();
                $table->unique(['batch_id', 'row_number'], 'circular_staging_batch_row_unique');
                $table->index(['batch_id', 'validation_status'], 'circular_staging_batch_status_idx');
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('exam');
        $schema->dropIfExists('circular_import_staging');
        $schema->dropIfExists('circular_import_batches');

        if ($schema->hasTable('circular_entries')) {
            $schema->table('circular_entries', function (Blueprint $table): void {
                $table->dropIndex('circular_entries_version_order_idx');
                $table->dropIndex('circular_entries_version_code_idx');
                $table->dropColumn([
                    'cadre_name_snapshot', 'cadre_name_bn_snapshot',
                    'post_name_snapshot', 'post_name_bn_snapshot', 'source',
                ]);
                $table->unique('effective_code');
            });
        }
    }
};
