<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $schema = Schema::connection('exam');

        if (! $schema->hasTable('circular_authority_previews')) {
            $schema->create('circular_authority_previews', function (Blueprint $table): void {
                $table->id();
                $table->unsignedInteger('version')->index();
                $table->char('dataset_hash', 64)->index();
                $table->string('file_path', 500);
                $table->unsignedBigInteger('generated_by')->nullable();
                $table->timestamp('generated_at');
                $table->json('summary')->nullable();
                $table->timestamps();
                $table->index(['version', 'generated_at']);
            });
        }

        if (! $schema->hasTable('circular_confirmations')) {
            $schema->create('circular_confirmations', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('authority_preview_id')->index();
                $table->unsignedInteger('version')->index();
                $table->char('dataset_hash', 64)->index();
                $table->unsignedBigInteger('confirmed_by')->nullable();
                $table->timestamp('confirmed_at');
                $table->text('confirmation_notes');
                $table->timestamps();
                $table->index(['version', 'confirmed_at']);
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('exam');
        $schema->dropIfExists('circular_confirmations');
        $schema->dropIfExists('circular_authority_previews');
    }
};
