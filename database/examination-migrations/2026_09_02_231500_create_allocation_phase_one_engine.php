<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('exam');

        $schema->create('allocation_runs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('version')->unique('alloc_run_ver_uq');
            $table->unsignedBigInteger('input_freeze_id')->index();
            $table->string('status', 40)->default('queued')->index();
            $table->string('phase', 40)->default('QUEUED')->index();
            $table->string('input_fingerprint', 64)->index();
            $table->string('queue_hash', 64)->index();
            $table->string('settings_hash', 64)->index();
            $table->string('seat_breakup_hash', 64)->index();
            $table->unsignedInteger('iteration_count')->default(0);
            $table->unsignedInteger('allocated_count')->default(0);
            $table->unsignedInteger('unallocated_count')->default(0);
            $table->unsignedInteger('mq_count')->default(0);
            $table->unsignedInteger('cff_count')->default(0);
            $table->unsignedInteger('em_count')->default(0);
            $table->unsignedInteger('phc_count')->default(0);
            $table->unsignedInteger('final_count')->default(0);
            $table->unsignedInteger('temporary_count')->default(0);
            $table->string('phase1_output_hash', 64)->nullable()->index();
            $table->string('seat_ledger_hash', 64)->nullable()->index();
            $table->text('failure_message')->nullable();
            $table->unsignedBigInteger('started_by')->nullable()->index();
            $table->timestamp('started_at')->nullable()->index();
            $table->timestamp('completed_at')->nullable()->index();
            $table->timestamps();
            $table->foreign('input_freeze_id', 'alloc_run_freeze_fk')
                ->references('id')->on('allocation_input_freezes')->restrictOnDelete();
        });

        $schema->create('allocation_results', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('allocation_run_id')->index();
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
            $table->string('decision_status', 20)->index();
            $table->string('decision_reason', 100)->nullable()->index();
            $table->timestamps();
            $table->unique(['allocation_run_id', 'registration_id'], 'alloc_result_candidate_uq');
            $table->foreign('allocation_run_id', 'alloc_result_run_fk')
                ->references('id')->on('allocation_runs')->cascadeOnDelete();
        });

        $schema->create('allocation_seat_ledgers', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('allocation_run_id')->index();
            $table->unsignedBigInteger('circular_entry_id')->index();
            $table->unsignedInteger('cadre_code')->index();
            $table->unsignedInteger('total_capacity');
            $table->unsignedInteger('mq_capacity');
            $table->unsignedInteger('cff_capacity');
            $table->unsignedInteger('em_capacity');
            $table->unsignedInteger('phc_capacity');
            $table->unsignedInteger('mq_occupied')->default(0);
            $table->unsignedInteger('cff_occupied')->default(0);
            $table->unsignedInteger('em_occupied')->default(0);
            $table->unsignedInteger('phc_occupied')->default(0);
            $table->unsignedInteger('mq_remaining')->default(0);
            $table->unsignedInteger('cff_remaining')->default(0);
            $table->unsignedInteger('em_remaining')->default(0);
            $table->unsignedInteger('phc_remaining')->default(0);
            $table->timestamps();
            $table->unique(['allocation_run_id', 'circular_entry_id'], 'alloc_ledger_run_entry_uq');
            $table->foreign('allocation_run_id', 'alloc_ledger_run_fk')
                ->references('id')->on('allocation_runs')->cascadeOnDelete();
        });

        $schema->create('allocation_decision_events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('allocation_run_id')->index();
            $table->unsignedInteger('sequence_no')->index();
            $table->unsignedInteger('iteration_no')->default(0)->index();
            $table->string('phase', 40)->index();
            $table->string('event', 60)->index();
            $table->unsignedBigInteger('registration_id')->nullable()->index();
            $table->unsignedBigInteger('circular_entry_id')->nullable()->index();
            $table->unsignedInteger('cadre_code')->nullable()->index();
            $table->string('allocation_basis', 10)->nullable()->index();
            $table->string('reason', 120)->nullable();
            $table->json('context')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
            $table->unique(['allocation_run_id', 'sequence_no'], 'alloc_event_run_seq_uq');
            $table->foreign('allocation_run_id', 'alloc_event_run_fk')
                ->references('id')->on('allocation_runs')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        $schema = Schema::connection('exam');
        $schema->dropIfExists('allocation_decision_events');
        $schema->dropIfExists('allocation_seat_ledgers');
        $schema->dropIfExists('allocation_results');
        $schema->dropIfExists('allocation_runs');
    }
};
