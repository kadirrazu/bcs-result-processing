<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CONNECTION = 'exam';

    public function up(): void
    {
        $schema = Schema::connection(self::CONNECTION);

        if (! $schema->hasTable('preliminary_finalization_runs')) {
            $schema->create('preliminary_finalization_runs', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('cutoff_decision_id')->index('prelim_final_cutoff_idx');
                $table->decimal('cutoff_mark', 6, 2);
                $table->string('status', 20)->default('queued')->index('prelim_final_status_idx');
                $table->text('reason');
                $table->unsignedBigInteger('queued_by');
                $table->timestamp('queued_at');
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->longText('failure_message')->nullable();
                $table->json('summary')->nullable();
                $table->timestamps();
            });
        }

        if (! $schema->hasColumn('preliminary_processing_states', 'latest_finalization_run_id')) {
            $schema->table('preliminary_processing_states', function (Blueprint $table): void {
                $table->unsignedBigInteger('latest_finalization_run_id')->nullable()->after('current_cutoff_decision_id');
                $table->index('latest_finalization_run_id', 'prelim_state_final_idx');
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection(self::CONNECTION);

        if ($schema->hasColumn('preliminary_processing_states', 'latest_finalization_run_id')) {
            $schema->table('preliminary_processing_states', function (Blueprint $table): void {
                $table->dropIndex('prelim_state_final_idx');
                $table->dropColumn('latest_finalization_run_id');
            });
        }

        $schema->dropIfExists('preliminary_finalization_runs');
    }
};
