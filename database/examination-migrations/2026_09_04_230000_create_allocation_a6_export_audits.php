<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::connection('exam')->create('allocation_a6_export_audits', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('allocation_a5_run_id')->index();
            $table->string('export_type', 40)->index();
            $table->string('scope', 40)->nullable()->index();
            $table->unsignedInteger('cadre_code')->nullable()->index();
            $table->json('parameters')->nullable();
            $table->string('a4_output_hash', 64)->nullable();
            $table->string('a5_candidate_hash', 64)->nullable();
            $table->string('a5_capacity_hash', 64)->nullable();
            $table->string('file_name')->nullable();
            $table->string('file_hash', 64)->nullable();
            $table->unsignedBigInteger('generated_by')->nullable()->index();
            $table->timestamp('generated_at')->index();
            $table->foreign('allocation_a5_run_id', 'a6_export_a5_fk')->references('id')->on('allocation_a5_runs')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection('exam')->dropIfExists('allocation_a6_export_audits');
    }
};
