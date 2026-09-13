<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('exam');

        $schema->create('reporting_dynamic_saved_reports', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 180)->index();
            $table->text('description')->nullable();
            $table->json('definition');
            $table->string('definition_hash', 64)->index();
            $table->unsignedInteger('version')->default(1);
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();
        });

        $schema->create('reporting_dynamic_run_history', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('saved_report_id')->index();
            $table->unsignedInteger('saved_report_version');
            $table->string('run_type', 20)->default('preview')->index();
            $table->json('definition_snapshot');
            $table->unsignedBigInteger('matching_count')->default(0);
            $table->unsignedInteger('preview_size')->default(0);
            $table->unsignedInteger('duration_ms')->default(0);
            $table->string('status', 20)->default('completed')->index();
            $table->text('error_message')->nullable();
            $table->unsignedBigInteger('executed_by')->nullable()->index();
            $table->timestamp('executed_at')->useCurrent()->index();
            $table->foreign('saved_report_id', 'rpt_dyn_hist_saved_fk')
                ->references('id')->on('reporting_dynamic_saved_reports')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        $schema = Schema::connection('exam');
        $schema->dropIfExists('reporting_dynamic_run_history');
        $schema->dropIfExists('reporting_dynamic_saved_reports');
    }
};
