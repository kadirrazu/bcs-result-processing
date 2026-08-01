<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('exam');

        if (! $schema->hasTable('written_reconciliation_reports')) {
            $schema->create('written_reconciliation_reports', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('source_batch_id')->index();
                $table->json('summary');
                $table->unsignedBigInteger('generated_by');
                $table->timestamp('generated_at');
                $table->timestamps();
            });
        }

        if (! $schema->hasTable('written_processing_runs')) {
            $schema->create('written_processing_runs', function (Blueprint $table): void {
                $table->id();
                $table->string('type', 40)->default('written_rules')->index();
                $table->string('status', 20)->default('queued')->index();
                $table->unsignedInteger('total_rows')->default(0);
                $table->unsignedInteger('processed_rows')->default(0);
                $table->decimal('progress_percent', 8, 4)->default(0);
                $table->string('current_step', 120)->nullable();
                $table->longText('failure_message')->nullable();
                $table->unsignedBigInteger('created_by');
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->timestamps();
            });
        }

        if (! $schema->hasColumn('written_processing_states', 'latest_reconciliation_report_id')) {
            $schema->table('written_processing_states', function (Blueprint $table): void {
                $table->unsignedBigInteger('latest_reconciliation_report_id')->nullable();
            });
        }
        if (! $schema->hasColumn('written_processing_states', 'latest_processing_run_id')) {
            $schema->table('written_processing_states', function (Blueprint $table): void {
                $table->unsignedBigInteger('latest_processing_run_id')->nullable();
            });
        }
        if (! $schema->hasColumn('written_processing_states', 'paper_crash_processed_at')) {
            $schema->table('written_processing_states', function (Blueprint $table): void {
                $table->timestamp('paper_crash_processed_at')->nullable();
            });
        }
        if (! $schema->hasColumn('written_processing_states', 'paper_crash_processed_by')) {
            $schema->table('written_processing_states', function (Blueprint $table): void {
                $table->unsignedBigInteger('paper_crash_processed_by')->nullable();
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('exam');
        foreach (['paper_crash_processed_by', 'paper_crash_processed_at', 'latest_processing_run_id', 'latest_reconciliation_report_id'] as $column) {
            if ($schema->hasTable('written_processing_states') && $schema->hasColumn('written_processing_states', $column)) {
                $schema->table('written_processing_states', function (Blueprint $table) use ($column): void {
                    $table->dropColumn($column);
                });
            }
        }
        $schema->dropIfExists('written_processing_runs');
        $schema->dropIfExists('written_reconciliation_reports');
    }
};
