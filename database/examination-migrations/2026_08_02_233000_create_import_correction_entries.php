<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('exam')->hasTable('import_correction_entries')) {
            return;
        }

        Schema::connection('exam')->create('import_correction_entries', function (Blueprint $table): void {
            $table->id();
            $table->string('module', 30)->index();
            $table->unsignedBigInteger('batch_id')->index();
            $table->unsignedBigInteger('staging_row_id')->index();
            $table->unsignedInteger('source_row')->index();
            $table->string('validation_status_before', 30)->nullable();
            $table->json('original_payload');
            $table->json('corrected_payload');
            $table->string('source_filename');
            $table->unsignedBigInteger('actor_id');
            $table->string('actor_name')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['module', 'batch_id', 'source_row'], 'import_corr_batch_row_idx');
        });
    }

    public function down(): void
    {
        Schema::connection('exam')->dropIfExists('import_correction_entries');
    }
};
