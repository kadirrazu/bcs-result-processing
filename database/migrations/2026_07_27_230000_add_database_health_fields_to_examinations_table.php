<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('examinations', function (Blueprint $table): void {
            $table->string('database_health_status', 20)->nullable()->after('is_enabled');
            $table->timestamp('database_checked_at')->nullable()->after('database_health_status');
            $table->unsignedInteger('database_migration_batch')->nullable()->after('database_checked_at');
            $table->text('database_health_error')->nullable()->after('database_migration_batch');
        });
    }

    public function down(): void
    {
        Schema::table('examinations', function (Blueprint $table): void {
            $table->dropColumn([
                'database_health_status',
                'database_checked_at',
                'database_migration_batch',
                'database_health_error',
            ]);
        });
    }
};
