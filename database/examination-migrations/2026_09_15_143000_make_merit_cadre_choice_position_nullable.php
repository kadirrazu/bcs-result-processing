<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::connection('exam')->table('merit_cadre_ranks', function (Blueprint $table): void {
            $table->unsignedInteger('choice_position')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Historical choice-dependent rows may contain values, but new eligibility-based rows are NULL.
        // Reversing safely requires regenerating Merit under the old business rule, so no destructive down conversion is attempted.
    }
};
