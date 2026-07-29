<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create central reference masters used by examination registrations.
     */
    public function up(): void
    {
        Schema::create('genders', function (Blueprint $table): void {
            $table->id();
            $table->unsignedTinyInteger('code')->unique();
            $table->string('name', 80);
            $table->string('name_bn', 120)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('divisions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedSmallInteger('code')->unique();
            $table->string('name', 120);
            $table->string('name_bn', 150)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('districts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedSmallInteger('code')->unique();
            $table->unsignedSmallInteger('division_code')->index();
            $table->string('name', 120);
            $table->string('name_bn', 150)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            // Composite lookup supports active district dropdowns by division.
            $table->index(['division_code', 'is_active']);
        });

        Schema::create('universities', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('code')->unique();
            $table->string('name', 255);
            $table->string('name_bn', 255)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('universities');
        Schema::dropIfExists('districts');
        Schema::dropIfExists('divisions');
        Schema::dropIfExists('genders');
    }
};
