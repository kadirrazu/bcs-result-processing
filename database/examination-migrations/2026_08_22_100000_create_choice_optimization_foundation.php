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

        $schema->create('choice_optimization_settings', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->boolean('optimization_enabled')->default(false)->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();
        });

        $schema->create('choice_optimization_processing_states', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->string('status', 40)->default('not_started')->index();
            $table->boolean('is_stale')->default(false)->index();
            $table->text('stale_reason')->nullable();
            $table->json('source_snapshot')->nullable();
            $table->string('dataset_hash', 64)->nullable()->index();
            $table->json('summary')->nullable();
            $table->unsignedBigInteger('finalized_by')->nullable()->index();
            $table->timestamp('finalized_at')->nullable()->index();
            $table->timestamps();
        });

        $schema->create('choice_optimization_processing_audits', function (Blueprint $table): void {
            $table->id();
            $table->string('event', 100)->index();
            $table->unsignedBigInteger('actor_id')->nullable()->index();
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40)->nullable();
            $table->json('context')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        DB::connection('exam')->table('choice_optimization_settings')->insert([
            'id' => 1,
            'optimization_enabled' => (bool) config('choice-optimization.default_enabled', false),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('exam')->table('choice_optimization_processing_states')->insert([
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
        $schema->dropIfExists('choice_optimization_processing_audits');
        $schema->dropIfExists('choice_optimization_processing_states');
        $schema->dropIfExists('choice_optimization_settings');
    }
};
