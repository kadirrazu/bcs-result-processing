<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $schema = Schema::connection('exam');

        // This migration may be re-run after an earlier MySQL identifier-length
        // failure. Add Circular columns only when they are still missing.
        if (! $schema->hasColumn('circular_entries', 'special_requirement')) {
            $schema->table('circular_entries', function (Blueprint $table): void {
                $table->boolean('special_requirement')
                    ->default(false)
                    ->index('circ_special_req_idx')
                    ->after('note');
            });
        }

        if (! $schema->hasColumn('circular_entries', 'special_requirement_comment')) {
            $schema->table('circular_entries', function (Blueprint $table): void {
                $table->text('special_requirement_comment')
                    ->nullable()
                    ->after('special_requirement');
            });
        }

        // The two event tables are introduced only by this migration. If an
        // earlier attempt failed before the migration was recorded, MySQL may
        // have left one/both tables behind. Recreate them cleanly so operators
        // do not need manual database cleanup before re-running the migration.
        $schema->dropIfExists('allocation_special_requirement_review_events');
        $schema->dropIfExists('manual_allocation_choice_adjustment_events');

        $schema->create('manual_allocation_choice_adjustment_events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('registration_id');
            $table->string('reg', 32);
            $table->unsignedInteger('choice_code');
            $table->string('action', 20); // EXCLUDE / RESTORE
            $table->text('reason');
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('registration_id', 'mac_registration_idx');
            $table->index('reg', 'mac_reg_idx');
            $table->index('choice_code', 'mac_choice_idx');
            $table->index('action', 'mac_action_idx');
            $table->index('actor_id', 'mac_actor_idx');
            $table->index('created_at', 'mac_created_idx');
            $table->index(['registration_id', 'choice_code', 'id'], 'mac_candidate_choice_event_idx');
        });

        $schema->create('allocation_special_requirement_review_events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('allocation_a5_run_id');
            $table->unsignedBigInteger('allocation_a4_run_id');
            $table->unsignedBigInteger('registration_id');
            $table->unsignedBigInteger('circular_entry_id');
            $table->string('status', 30); // VERIFIED / FAILED
            $table->text('note')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('allocation_a5_run_id', 'asr_a5_idx');
            $table->index('allocation_a4_run_id', 'asr_a4_idx');
            $table->index('registration_id', 'asr_registration_idx');
            $table->index('circular_entry_id', 'asr_circular_entry_idx');
            $table->index('status', 'asr_status_idx');
            $table->index('actor_id', 'asr_actor_idx');
            $table->index('created_at', 'asr_created_idx');
            $table->index(['allocation_a5_run_id', 'registration_id', 'circular_entry_id', 'id'], 'asr_latest_review_idx');
        });
    }

    public function down(): void
    {
        $schema = Schema::connection('exam');
        $schema->dropIfExists('allocation_special_requirement_review_events');
        $schema->dropIfExists('manual_allocation_choice_adjustment_events');

        if ($schema->hasColumn('circular_entries', 'special_requirement_comment')) {
            $schema->table('circular_entries', function (Blueprint $table): void {
                $table->dropColumn('special_requirement_comment');
            });
        }

        if ($schema->hasColumn('circular_entries', 'special_requirement')) {
            $schema->table('circular_entries', function (Blueprint $table): void {
                $table->dropColumn('special_requirement');
            });
        }
    }
};
