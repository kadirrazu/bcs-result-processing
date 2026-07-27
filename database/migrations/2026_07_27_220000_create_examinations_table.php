<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('examinations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedSmallInteger('bcs_number')->unique();
            $table->string('name', 150);
            $table->string('slug', 80)->unique();
            $table->string('database_name', 64)->unique();
            $table->string('status', 20)->default('draft')->index();
            $table->boolean('is_enabled')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('examinations');
    }
};
