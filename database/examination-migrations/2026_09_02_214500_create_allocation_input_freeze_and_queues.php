<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('exam');

        $schema->create('allocation_input_freezes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('version')->unique('alloc_input_freeze_ver_uq');
            $table->string('status', 30)->default('frozen')->index();
            $table->string('choice_source', 60)->index();
            $table->json('source_snapshot');
            $table->string('registration_hash', 64)->index();
            $table->string('circular_hash', 64)->index();
            $table->string('choice_hash', 64)->index();
            $table->string('merit_hash', 64)->index();
            $table->string('settings_hash', 64)->index();
            $table->string('seat_breakup_hash', 64)->index();
            $table->string('input_fingerprint', 64)->index();
            $table->string('queue_hash', 64)->index();
            $table->unsignedInteger('total_candidates')->default(0);
            $table->unsignedInteger('choice_ready_candidates')->default(0);
            $table->unsignedInteger('total_queue_entries')->default(0);
            $table->unsignedInteger('skipped_choice_entries')->default(0);
            $table->unsignedBigInteger('frozen_by')->nullable()->index();
            $table->timestamp('frozen_at')->index();
            $table->timestamps();
        });

        $schema->create('allocation_input_candidates', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('input_freeze_id')->index();
            $table->unsignedBigInteger('registration_id')->index();
            $table->unsignedBigInteger('merit_result_id')->index();
            $table->string('user_id', 20)->index();
            $table->string('reg', 20)->index();
            $table->string('cadre_category', 2)->nullable()->index();
            $table->unsignedInteger('general_merit_position')->nullable()->index();
            $table->json('quota_entitlement');
            $table->json('choice_codes');
            $table->string('choice_source', 60)->index();
            $table->string('skip_reason', 100)->nullable()->index();
            $table->timestamps();
            $table->unique(['input_freeze_id', 'registration_id'], 'alloc_input_candidate_uq');
            $table->foreign('input_freeze_id', 'alloc_input_candidate_freeze_fk')
                ->references('id')->on('allocation_input_freezes')->cascadeOnDelete();
        });

        $schema->create('allocation_input_queue_entries', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('input_freeze_id')->index();
            $table->unsignedBigInteger('registration_id')->index();
            $table->unsignedBigInteger('circular_entry_id')->index();
            $table->unsignedInteger('cadre_code')->index();
            $table->string('cadre_type', 2)->index();
            $table->unsignedInteger('choice_position')->index();
            $table->unsignedInteger('merit_position')->index();
            $table->string('merit_source', 30)->index();
            $table->unsignedInteger('general_merit_position')->nullable()->index();
            $table->unsignedInteger('technical_merit_position')->nullable()->index();
            $table->boolean('eligible_cff')->default(false)->index();
            $table->boolean('eligible_em')->default(false)->index();
            $table->boolean('eligible_phc')->default(false)->index();
            $table->unsignedInteger('total_post');
            $table->unsignedInteger('mq');
            $table->unsignedInteger('cff');
            $table->unsignedInteger('em');
            $table->unsignedInteger('phc');
            $table->string('queue_key', 80)->index();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['input_freeze_id', 'registration_id', 'cadre_code'], 'alloc_input_queue_candidate_cadre_uq');
            $table->index(['input_freeze_id', 'cadre_code', 'merit_position', 'registration_id'], 'alloc_input_queue_order_idx');
            $table->foreign('input_freeze_id', 'alloc_input_queue_freeze_fk')
                ->references('id')->on('allocation_input_freezes')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        $schema = Schema::connection('exam');
        $schema->dropIfExists('allocation_input_queue_entries');
        $schema->dropIfExists('allocation_input_candidates');
        $schema->dropIfExists('allocation_input_freezes');
    }
};
