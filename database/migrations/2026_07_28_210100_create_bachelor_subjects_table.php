<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bachelor_subjects', function (Blueprint $table): void {
            $table->id();
            $table->string('subject_code', 30)->unique();
            $table->string('subject_name');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bachelor_subjects');
    }
};
