<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('exam');

        $schema->create('allocation_a4_runs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('version')->unique('alloc_a4_run_ver_uq');
            $table->unsignedBigInteger('phase1_run_id')->index();
            $table->unsignedBigInteger('input_freeze_id')->index();
            $table->string('status', 40)->default('queued')->index();
            $table->string('phase', 50)->default('QUEUED')->index();
            $table->string('input_fingerprint', 64)->index();
            $table->string('queue_hash', 64)->index();
            $table->string('phase1_output_hash', 64)->index();
            $table->string('phase1_seat_ledger_hash', 64)->index();
            $table->unsignedInteger('iteration_count')->default(0);
            $table->unsignedInteger('progress_percent')->default(0);
            $table->unsignedInteger('progress_current')->default(0);
            $table->unsignedInteger('progress_total')->default(0);
            $table->text('progress_message')->nullable();
            $table->unsignedInteger('allocated_count')->default(0);
            $table->unsignedInteger('unallocated_count')->default(0);
            $table->unsignedInteger('mq_count')->default(0);
            $table->unsignedInteger('cff_count')->default(0);
            $table->unsignedInteger('em_count')->default(0);
            $table->unsignedInteger('phc_count')->default(0);
            $table->unsignedInteger('nm_count')->default(0);
            $table->unsignedInteger('shifted_count')->default(0);
            $table->unsignedInteger('quota_to_merit_count')->default(0);
            $table->string('a4_output_hash', 64)->nullable()->index();
            $table->string('seat_ledger_hash', 64)->nullable()->index();
            $table->string('movement_hash', 64)->nullable()->index();
            $table->text('failure_message')->nullable();
            $table->unsignedBigInteger('started_by')->nullable()->index();
            $table->timestamp('started_at')->nullable()->index();
            $table->timestamp('completed_at')->nullable()->index();
            $table->timestamps();

            $table->foreign('phase1_run_id', 'alloc_a4_run_p1_fk')
                ->references('id')->on('allocation_runs')->restrictOnDelete();
            $table->foreign('input_freeze_id', 'alloc_a4_run_freeze_fk')
                ->references('id')->on('allocation_input_freezes')->restrictOnDelete();
        });

        $schema->create('allocation_a4_results', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('allocation_a4_run_id')->index();
            $table->unsignedBigInteger('input_candidate_id')->index();
            $table->unsignedBigInteger('registration_id')->index();
            $table->string('reg', 20)->index();
            $table->unsignedBigInteger('circular_entry_id')->index();
            $table->unsignedInteger('cadre_code')->index();
            $table->string('cadre_type', 2)->index();
            $table->unsignedInteger('choice_position')->index();
            $table->unsignedInteger('merit_position')->index();
            $table->string('merit_source', 30)->index();
            $table->string('allocation_basis', 10)->index();
            $table->string('movement_type', 20)->default('DIRECT')->index();
            $table->string('decision_status', 20)->default('FINAL')->index();
            $table->string('decision_reason', 120)->nullable()->index();

            // Original A3 assignment is kept on the A4 row for fast operator review.
            $table->unsignedBigInteger('original_circular_entry_id')->nullable()->index();
            $table->unsignedInteger('original_cadre_code')->nullable()->index();
            $table->unsignedInteger('original_choice_position')->nullable();
            $table->string('original_allocation_basis', 10)->nullable()->index();
            $table->timestamps();
            $table->unique(['allocation_a4_run_id', 'registration_id'], 'alloc_a4_result_candidate_uq');
            $table->foreign('allocation_a4_run_id', 'alloc_a4_result_run_fk')
                ->references('id')->on('allocation_a4_runs')->cascadeOnDelete();
        });

        $schema->create('allocation_a4_seat_ledgers', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('allocation_a4_run_id')->index();
            $table->unsignedBigInteger('circular_entry_id')->index();
            $table->unsignedInteger('cadre_code')->index();
            $table->unsignedInteger('total_capacity');
            $table->unsignedInteger('mq_capacity');
            $table->unsignedInteger('cff_capacity');
            $table->unsignedInteger('em_capacity');
            $table->unsignedInteger('phc_capacity');
            $table->unsignedInteger('converted_cff')->default(0);
            $table->unsignedInteger('converted_em')->default(0);
            $table->unsignedInteger('converted_phc')->default(0);
            $table->unsignedInteger('merit_capacity')->default(0);
            $table->unsignedInteger('mq_occupied')->default(0);
            $table->unsignedInteger('cff_occupied')->default(0);
            $table->unsignedInteger('em_occupied')->default(0);
            $table->unsignedInteger('phc_occupied')->default(0);
            $table->unsignedInteger('total_occupied')->default(0);
            $table->unsignedInteger('total_remaining')->default(0);
            $table->unsignedInteger('nm_count')->default(0);
            $table->unsignedInteger('shifted_count')->default(0);
            $table->unsignedInteger('quota_to_merit_count')->default(0);
            $table->timestamps();
            $table->unique(['allocation_a4_run_id', 'circular_entry_id'], 'alloc_a4_ledger_run_entry_uq');
            $table->foreign('allocation_a4_run_id', 'alloc_a4_ledger_run_fk')
                ->references('id')->on('allocation_a4_runs')->cascadeOnDelete();
        });

        $schema->create('allocation_a4_movement_events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('allocation_a4_run_id')->index();
            $table->unsignedInteger('sequence_no')->index();
            $table->unsignedBigInteger('actor_id')->nullable()->index();
            $table->unsignedInteger('iteration_no')->default(0)->index();
            $table->string('event', 70)->index();
            $table->unsignedBigInteger('registration_id')->nullable()->index();
            $table->unsignedBigInteger('from_circular_entry_id')->nullable()->index();
            $table->unsignedInteger('from_cadre_code')->nullable()->index();
            $table->string('from_basis', 10)->nullable()->index();
            $table->unsignedInteger('from_choice_position')->nullable();
            $table->unsignedBigInteger('to_circular_entry_id')->nullable()->index();
            $table->unsignedInteger('to_cadre_code')->nullable()->index();
            $table->string('to_basis', 10)->nullable()->index();
            $table->unsignedInteger('to_choice_position')->nullable();
            $table->unsignedInteger('target_merit_position')->nullable()->index();
            $table->string('movement_type', 20)->nullable()->index();
            $table->string('reason', 140)->nullable()->index();
            $table->string('converted_from', 10)->nullable()->index();
            $table->json('context')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
            $table->unique(['allocation_a4_run_id', 'sequence_no'], 'alloc_a4_event_run_seq_uq');
            $table->foreign('allocation_a4_run_id', 'alloc_a4_event_run_fk')
                ->references('id')->on('allocation_a4_runs')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        $schema = Schema::connection('exam');
        $schema->dropIfExists('allocation_a4_movement_events');
        $schema->dropIfExists('allocation_a4_seat_ledgers');
        $schema->dropIfExists('allocation_a4_results');
        $schema->dropIfExists('allocation_a4_runs');
    }
};
