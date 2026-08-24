<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('exam')->create('choice_optimization_historical_choices', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('registration_id');
            $table->string('reg', 32);
            $table->string('input_choice_source', 40);
            $table->json('input_choice_codes');
            $table->json('historical_recommendations')->nullable();
            $table->json('matched_cutoff')->nullable();
            $table->json('removed_choice_codes')->nullable();
            $table->json('final_choice_codes');
            $table->string('optimization_status', 64)->index('co_hist_choice_status_idx');
            $table->json('warnings')->nullable();
            $table->json('blocking_issues')->nullable();
            $table->unsignedBigInteger('processed_by')->nullable()->index('co_hist_choice_actor_idx');
            $table->timestamp('processed_at')->nullable()->index('co_hist_choice_time_idx');
            $table->timestamps();

            $table->unique('registration_id', 'co_hist_choice_reg_uq');
            $table->index('reg', 'co_hist_choice_reg_idx');
        });
    }

    public function down(): void
    {
        Schema::connection('exam')->dropIfExists('choice_optimization_historical_choices');
    }
};
