<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::connection('exam')->create('historical_punishment_screening_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('phase', 20);
            $table->date('reference_date');
            $table->unsignedInteger('candidate_count')->default(0);
            $table->unsignedInteger('matched_count')->default(0);
            $table->unsignedInteger('review_count')->default(0);
            $table->unsignedInteger('active_warning_count')->default(0);
            $table->string('matching_algorithm', 60);
            $table->foreignId('created_by')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['phase','created_at'], 'hist_pun_screen_phase_idx');
        });
        Schema::connection('exam')->create('historical_punishment_screening_matches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('run_id')->constrained('historical_punishment_screening_runs')->cascadeOnDelete();
            $table->unsignedBigInteger('registration_id');
            $table->string('user_id', 40)->nullable();
            $table->string('reg', 40)->nullable();
            $table->string('name')->nullable();
            $table->unsignedBigInteger('historical_punishment_id');
            $table->string('match_status', 20);
            $table->string('match_method', 60);
            $table->json('evidence')->nullable();
            $table->boolean('punishment_active')->default(false);
            $table->string('punishment_state', 30);
            $table->foreignId('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();
            $table->index(['run_id','match_status'], 'hist_pun_match_run_status_idx');
            $table->index(['run_id','punishment_active'], 'hist_pun_match_run_active_idx');
        });
    }
    public function down(): void
    {
        Schema::connection('exam')->dropIfExists('historical_punishment_screening_matches');
        Schema::connection('exam')->dropIfExists('historical_punishment_screening_runs');
    }
};
