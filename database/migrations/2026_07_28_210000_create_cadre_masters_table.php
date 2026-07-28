<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cadre_masters', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('cadre_code')->unique();
            $table->string('cadre_abbr', 20)->unique();
            $table->string('cadre_title');
            $table->string('cadre_title_bn');
            $table->string('cadre_type', 2);
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->index(['cadre_type', 'display_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cadre_masters');
    }
};
