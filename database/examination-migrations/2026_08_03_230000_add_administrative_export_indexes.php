<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('exam')->table('preliminary_results', function (Blueprint $table): void {
            // Mark-sorted administrative exports use this index while REG already has a unique index.
            $table->index(['mark', 'reg'], 'preli_mark_reg_export_idx');
        });

        Schema::connection('exam')->table('written_candidate_marks', function (Blueprint $table): void {
            // Covers the subject-mark pivot used by Written administrative exports.
            $table->index(['written_result_id', 'subject_code', 'actual_mark'], 'wcm_export_cover_idx');
        });
    }

    public function down(): void
    {
        Schema::connection('exam')->table('written_candidate_marks', function (Blueprint $table): void {
            $table->dropIndex('wcm_export_cover_idx');
        });

        Schema::connection('exam')->table('preliminary_results', function (Blueprint $table): void {
            $table->dropIndex('preli_mark_reg_export_idx');
        });
    }
};
