<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'exam';

    public function up(): void
    {
        Schema::connection($this->connection)->table('registrations', function (Blueprint $table): void {
            // Rolls remain strings so leading zeroes are preserved for historical matching.
            $table->string('ssc_roll', 30)->nullable()->after('birth_date')->index();
            $table->unsignedSmallInteger('ssc_year')->nullable()->after('ssc_roll');
            $table->string('hsc_roll', 30)->nullable()->after('ssc_year')->index();
            $table->unsignedSmallInteger('hsc_year')->nullable()->after('hsc_roll');
            $table->unsignedSmallInteger('graduation_year')->nullable()->after('hsc_year');
        });

        Schema::connection($this->connection)->table('registration_import_staging', function (Blueprint $table): void {
            $table->string('ssc_roll', 50)->nullable()->after('birth_date');
            $table->string('ssc_year', 20)->nullable()->after('ssc_roll');
            $table->string('hsc_roll', 50)->nullable()->after('ssc_year');
            $table->string('hsc_year', 20)->nullable()->after('hsc_roll');
            $table->string('graduation_year', 20)->nullable()->after('hsc_year');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->table('registration_import_staging', function (Blueprint $table): void {
            $table->dropColumn(['ssc_roll', 'ssc_year', 'hsc_roll', 'hsc_year', 'graduation_year']);
        });

        Schema::connection($this->connection)->table('registrations', function (Blueprint $table): void {
            $table->dropIndex(['ssc_roll']);
            $table->dropIndex(['hsc_roll']);
            $table->dropColumn(['ssc_roll', 'ssc_year', 'hsc_roll', 'hsc_year', 'graduation_year']);
        });
    }
};
