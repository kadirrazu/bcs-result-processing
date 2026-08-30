<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('exam');

        if ($schema->hasTable('choice_optimization_consolidated_historical_recommendations')) {
            $required = ['registration_id', 'current_reg', 'previous_bcs_number', 'cadre', 'consolidation_status', 'sources', 'conflict_cadres', 'generated_at'];
            $complete = collect($required)->every(fn (string $column): bool =>
                $schema->hasColumn('choice_optimization_consolidated_historical_recommendations', $column)
            );

            if ($complete) {
                return;
            }

            // Safe retry after a failed first attempt: this table is a generated
            // snapshot and has no authoritative user-entered data of its own.
            $schema->dropIfExists('choice_optimization_consolidated_historical_recommendations');
        }

        $schema->create('choice_optimization_consolidated_historical_recommendations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('registration_id')->index('co_chr_reg_id_idx');
            $table->string('current_reg', 50)->index('co_chr_current_reg_idx');
            $table->unsignedSmallInteger('previous_bcs_number')->index('co_chr_prev_bcs_idx');
            $table->string('cadre', 50)->nullable()->index('co_chr_cadre_idx');
            $table->string('consolidation_status', 20)->default('resolved')->index('co_chr_status_idx');
            $table->json('sources');
            $table->json('conflict_cadres')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->unique(['registration_id', 'previous_bcs_number'], 'co_chr_reg_bcs_uq');
        });
    }

    public function down(): void
    {
        Schema::connection('exam')->dropIfExists('choice_optimization_consolidated_historical_recommendations');
    }
};
