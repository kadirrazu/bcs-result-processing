<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('exam')->table('choice_optimization_historical_matches', function (Blueprint $table): void {
            $table->string('resolution_status', 32)->nullable()->after('match_evidence')
                ->index('co_hist_match_resolution_idx');
            $table->text('resolution_reason')->nullable()->after('resolution_status');
            $table->unsignedBigInteger('resolved_by')->nullable()->after('resolution_reason')
                ->index('co_hist_match_resolved_by_idx');
            $table->timestamp('resolved_at')->nullable()->after('resolved_by')
                ->index('co_hist_match_resolved_at_idx');
        });

        DB::connection('exam')
            ->table('choice_optimization_historical_matches')
            ->where('match_status', 'matched')
            ->update(['resolution_status' => 'auto_confirmed']);

        DB::connection('exam')
            ->table('choice_optimization_historical_matches')
            ->where('match_status', 'review')
            ->update(['resolution_status' => 'pending']);
    }

    public function down(): void
    {
        Schema::connection('exam')->table('choice_optimization_historical_matches', function (Blueprint $table): void {
            $table->dropIndex('co_hist_match_resolution_idx');
            $table->dropIndex('co_hist_match_resolved_by_idx');
            $table->dropIndex('co_hist_match_resolved_at_idx');
            $table->dropColumn([
                'resolution_status',
                'resolution_reason',
                'resolved_by',
                'resolved_at',
            ]);
        });
    }
};
