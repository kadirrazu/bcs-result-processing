<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('exam');

        $schema->create('allocation_result_disposition_states', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('allocation_a5_run_id')->unique('alloc_disp_state_a5_uq');
            $table->unsignedInteger('revision')->default(0);
            $table->string('disposition_hash', 64)->nullable()->index();
            $table->unsignedInteger('active_count')->default(0);
            $table->unsignedInteger('withheld_count')->default(0);
            $table->unsignedInteger('cancelled_count')->default(0);
            $table->unsignedBigInteger('changed_by')->nullable()->index();
            $table->timestamp('changed_at')->nullable()->index();
            $table->timestamps();
            $table->foreign('allocation_a5_run_id', 'alloc_disp_state_a5_fk')->references('id')->on('allocation_a5_runs')->cascadeOnDelete();
        });

        $schema->create('allocation_result_dispositions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('allocation_a5_run_id')->index();
            $table->unsignedBigInteger('registration_id')->index();
            $table->string('reg', 20)->index();
            $table->unsignedBigInteger('circular_entry_id')->index();
            $table->unsignedInteger('cadre_code')->index();
            $table->string('status', 20)->default('ACTIVE')->index();
            $table->text('reason')->nullable();
            $table->string('reference_no', 150)->nullable();
            $table->unsignedBigInteger('changed_by')->nullable()->index();
            $table->timestamp('changed_at')->nullable()->index();
            $table->timestamps();
            $table->unique(['allocation_a5_run_id','registration_id'], 'alloc_disp_candidate_uq');
            $table->foreign('allocation_a5_run_id', 'alloc_disp_a5_fk')->references('id')->on('allocation_a5_runs')->cascadeOnDelete();
        });

        $schema->create('allocation_result_disposition_audits', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('allocation_a5_run_id')->index();
            $table->unsignedBigInteger('registration_id')->index();
            $table->string('reg', 20)->index();
            $table->unsignedInteger('cadre_code')->index();
            $table->string('from_status', 20)->index();
            $table->string('to_status', 20)->index();
            $table->text('reason');
            $table->string('reference_no', 150)->nullable();
            $table->unsignedBigInteger('actor_id')->nullable()->index();
            $table->timestamp('created_at')->useCurrent()->index();
            $table->foreign('allocation_a5_run_id', 'alloc_disp_audit_a5_fk')->references('id')->on('allocation_a5_runs')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        $schema = Schema::connection('exam');
        $schema->dropIfExists('allocation_result_disposition_audits');
        $schema->dropIfExists('allocation_result_dispositions');
        $schema->dropIfExists('allocation_result_disposition_states');
    }
};
