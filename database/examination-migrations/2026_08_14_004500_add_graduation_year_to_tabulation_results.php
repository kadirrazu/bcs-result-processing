<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('exam')->table('tabulation_results', function (Blueprint $table): void {
            $table->unsignedSmallInteger('graduation_year')->nullable()->after('birth_date')->index();
        });
    }

    public function down(): void
    {
        Schema::connection('exam')->table('tabulation_results', function (Blueprint $table): void {
            $table->dropColumn('graduation_year');
        });
    }
};
