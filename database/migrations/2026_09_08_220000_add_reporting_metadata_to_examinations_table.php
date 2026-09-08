<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('examinations', function (Blueprint $table): void {
            $table->string('bcs_type', 20)->nullable()->after('database_name')->index();
            $table->date('advertisement_date')->nullable()->after('bcs_type');
            $table->date('age_calculation_date')->nullable()->after('advertisement_date');
            $table->boolean('is_completed')->default(false)->after('is_enabled')->index();
        });
    }

    public function down(): void
    {
        Schema::table('examinations', function (Blueprint $table): void {
            $table->dropIndex(['bcs_type']);
            $table->dropIndex(['is_completed']);
            $table->dropColumn(['bcs_type', 'advertisement_date', 'age_calculation_date', 'is_completed']);
        });
    }
};
