<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('exam');

        $schema->create('non_cadre_circular_imports', function (Blueprint $table): void {
            $table->id();
            $table->string('status', 30)->default('staged')->index();
            $table->string('source_filename');
            $table->string('source_hash', 64)->nullable()->index();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('valid_rows')->default(0);
            $table->unsignedInteger('invalid_rows')->default(0);
            $table->unsignedBigInteger('uploaded_by')->nullable()->index();
            $table->unsignedBigInteger('approved_by')->nullable()->index();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('approved_version_id')->nullable()->index();
            $table->timestamps();
        });

        $schema->create('non_cadre_circular_import_rows', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('import_id')->index();
            $table->unsignedInteger('row_number');
            $table->json('raw_data');
            $table->json('normalized_data')->nullable();
            $table->string('validation_status', 20)->default('pending')->index();
            $table->json('validation_errors')->nullable();
            $table->timestamps();
            $table->foreign('import_id', 'nc_circ_import_row_import_fk')->references('id')->on('non_cadre_circular_imports')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        $schema = Schema::connection('exam');
        $schema->dropIfExists('non_cadre_circular_import_rows');
        $schema->dropIfExists('non_cadre_circular_imports');
    }
};
