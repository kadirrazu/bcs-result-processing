<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('exam');

        $schema->create('allocation_settings', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->json('quota_priority');
            $table->unsignedTinyInteger('small_cadre_quota_threshold')->default(10);
            $table->unsignedTinyInteger('mq_percent')->default(93);
            $table->unsignedTinyInteger('cff_percent')->default(5);
            $table->unsignedTinyInteger('em_percent')->default(1);
            $table->unsignedTinyInteger('phc_percent')->default(1);
            $table->string('status', 20)->default('draft')->index();
            $table->string('settings_hash', 64)->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->unsignedBigInteger('finalized_by')->nullable()->index();
            $table->timestamp('finalized_at')->nullable()->index();
            $table->timestamps();
        });

        $schema->create('allocation_seat_breakup_versions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('version')->unique('alloc_seat_ver_uq');
            $table->string('status', 30)->default('draft')->index();
            $table->unsignedInteger('circular_version')->index();
            $table->string('circular_hash', 64)->index();
            $table->string('dataset_hash', 64)->nullable()->index();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('total_posts')->default(0);
            $table->unsignedInteger('mq_posts')->default(0);
            $table->unsignedInteger('cff_posts')->default(0);
            $table->unsignedInteger('em_posts')->default(0);
            $table->unsignedInteger('phc_posts')->default(0);
            $table->string('source_file')->nullable();
            $table->json('validation_summary')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('finalized_by')->nullable()->index();
            $table->timestamp('finalized_at')->nullable()->index();
            $table->timestamps();
        });

        $schema->create('allocation_seat_breakup_rows', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('seat_breakup_version_id')->index();
            $table->string('sl', 20);
            $table->unsignedInteger('cadre_code')->index();
            $table->unsignedInteger('total_post');
            $table->unsignedInteger('mq');
            $table->unsignedInteger('cff');
            $table->unsignedInteger('em');
            $table->unsignedInteger('phc');
            $table->unsignedBigInteger('circular_entry_id')->index();
            $table->timestamps();
            $table->unique(['seat_breakup_version_id', 'circular_entry_id'], 'alloc_seat_ver_entry_uq');
            $table->foreign('seat_breakup_version_id', 'alloc_seat_row_ver_fk')->references('id')->on('allocation_seat_breakup_versions')->cascadeOnDelete();
        });

        $schema->create('allocation_processing_states', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->string('status', 40)->default('not_started')->index();
            $table->unsignedBigInteger('finalized_seat_breakup_version_id')->nullable()->index('alloc_state_seat_ver_idx');
            $table->boolean('is_stale')->default(false)->index();
            $table->text('stale_reason')->nullable();
            $table->json('source_snapshot')->nullable();
            $table->string('input_fingerprint', 64)->nullable()->index();
            $table->string('output_hash', 64)->nullable()->index();
            $table->unsignedBigInteger('finalized_by')->nullable()->index();
            $table->timestamp('finalized_at')->nullable()->index();
            $table->timestamps();
        });

        $schema->create('allocation_processing_audits', function (Blueprint $table): void {
            $table->id();
            $table->string('event', 100)->index();
            $table->unsignedBigInteger('actor_id')->nullable()->index();
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40)->nullable();
            $table->json('context')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        DB::connection('exam')->table('allocation_settings')->insert([
            'id' => 1,
            'quota_priority' => json_encode(config('allocation.default_quota_priority', ['CFF', 'EM', 'PHC'])),
            'small_cadre_quota_threshold' => (int) config('allocation.small_cadre_quota_threshold', 10),
            'mq_percent' => (int) config('allocation.default_breakup_percentages.mq', 93),
            'cff_percent' => (int) config('allocation.default_breakup_percentages.cff', 5),
            'em_percent' => (int) config('allocation.default_breakup_percentages.em', 1),
            'phc_percent' => (int) config('allocation.default_breakup_percentages.phc', 1),
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('exam')->table('allocation_processing_states')->insert([
            'id' => 1,
            'status' => 'not_started',
            'is_stale' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        $schema = Schema::connection('exam');
        $schema->dropIfExists('allocation_processing_audits');
        $schema->dropIfExists('allocation_processing_states');
        $schema->dropIfExists('allocation_seat_breakup_rows');
        $schema->dropIfExists('allocation_seat_breakup_versions');
        $schema->dropIfExists('allocation_settings');
    }
};
