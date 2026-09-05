<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::connection('exam')->create('reporting_export_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('module', 60)->index();
            $table->string('export_type', 40)->index();
            $table->string('scope', 60)->nullable()->index();
            $table->string('status', 30)->default('queued')->index();
            $table->string('phase', 60)->nullable();
            $table->unsignedTinyInteger('progress_percent')->default(0);
            $table->unsignedBigInteger('progress_current')->default(0);
            $table->unsignedBigInteger('progress_total')->default(0);
            $table->string('progress_message', 500)->nullable();
            $table->json('parameters')->nullable();
            $table->json('source_snapshot')->nullable();
            $table->string('file_path', 1000)->nullable();
            $table->string('file_name')->nullable();
            $table->string('file_mime', 150)->nullable();
            $table->string('file_hash', 64)->nullable();
            $table->unsignedBigInteger('generated_by')->nullable()->index();
            $table->timestamp('queued_at')->nullable()->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable()->index();
            $table->text('failure_message')->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection('exam')->dropIfExists('reporting_export_runs');
    }
};
