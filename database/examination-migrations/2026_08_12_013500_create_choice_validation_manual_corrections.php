<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('exam')->create('choice_validation_manual_corrections', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('choice_source_id');
            $table->foreign('choice_source_id', 'cv_manual_corr_source_fk')
                ->references('id')->on('choice_validation_sources')->cascadeOnDelete();
            $table->unsignedBigInteger('registration_id')->index();
            $table->unsignedInteger('source_version')->index();
            $table->unsignedInteger('validation_version')->nullable()->index();
            $table->json('before_snapshot');
            $table->json('corrected_snapshot');
            $table->json('changed_positions');
            $table->text('reason');
            $table->unsignedBigInteger('actor_id')->index();
            $table->string('actor_name')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('revalidated_at')->nullable()->index();
            $table->timestamp('created_at')->useCurrent()->index();
            $table->index(['choice_source_id', 'source_version'], 'cv_manual_corr_source_ver_idx');
        });
    }

    public function down(): void
    {
        Schema::connection('exam')->dropIfExists('choice_validation_manual_corrections');
    }
};
