<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('cadre_sub_masters');
        Schema::dropIfExists('cadre_masters');

        Schema::create('cadre_masters', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('cadre_code')->unique();
            $table->string('cadre_abbr', 20)->unique();
            $table->string('cadre_name');
            $table->string('cadre_name_bn');
            $table->string('post_name')->nullable();
            $table->string('post_name_bn')->nullable();
            $table->string('cadre_type', 2);
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->index(['cadre_type', 'display_order']);
        });

        Schema::create('cadre_sub_masters', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_cadre_id')->constrained('cadre_masters')->restrictOnDelete();
            $table->unsignedInteger('sub_cadre_code')->unique();
            $table->string('sub_cadre_abbr', 20)->nullable()->unique();
            $table->string('post_name');
            $table->string('post_name_bn');
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->unique(['parent_cadre_id', 'sub_cadre_code']);
            $table->index(['parent_cadre_id', 'display_order']);
        });

        Schema::create('master_data_audits', function (Blueprint $table): void {
            $table->id();
            $table->string('module', 50)->default('master_data')->index();
            $table->string('entity_type', 100)->index();
            $table->unsignedBigInteger('entity_id')->nullable()->index();
            $table->string('action', 60)->index();
            $table->unsignedBigInteger('actor_id')->nullable()->index();
            $table->string('actor_name')->nullable();
            $table->text('reason')->nullable();
            $table->json('changed_fields')->nullable();
            $table->json('before_snapshot')->nullable();
            $table->json('after_snapshot')->nullable();
            $table->string('ip_address', 64)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_data_audits');
        Schema::dropIfExists('cadre_sub_masters');
        Schema::dropIfExists('cadre_masters');

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
};
