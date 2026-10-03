<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::connection('exam')->table('historical_punishment_screening_runs', function (Blueprint $table): void {
            $table->string('status', 20)->default('completed')->after('matching_algorithm');
            $table->unsignedInteger('processed_count')->default(0)->after('candidate_count');
            $table->text('failure_message')->nullable()->after('status');
            $table->timestamp('started_at')->nullable()->after('failure_message');
            $table->timestamp('finished_at')->nullable()->after('started_at');
            $table->index(['phase', 'status'], 'hist_pun_screen_phase_status_idx');
        });

        DB::connection('exam')->table('historical_punishment_screening_runs')->update([
            'status' => 'completed',
            'processed_count' => DB::raw('candidate_count'),
        ]);
    }

    public function down(): void
    {
        Schema::connection('exam')->table('historical_punishment_screening_runs', function (Blueprint $table): void {
            $table->dropIndex('hist_pun_screen_phase_status_idx');
            $table->dropColumn(['status', 'processed_count', 'failure_message', 'started_at', 'finished_at']);
        });
    }
};
