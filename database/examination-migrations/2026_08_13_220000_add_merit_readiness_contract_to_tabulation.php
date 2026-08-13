<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('exam');

        $schema->table('tabulation_results', function (Blueprint $table): void {
            $table->unsignedTinyInteger('cadre_category')->nullable()->after('reg')->index();
            $table->date('birth_date')->nullable()->after('cadre_category')->index();
        });

        $schema->table('tabulation_processing_runs', function (Blueprint $table): void {
            $table->char('dataset_hash', 64)->nullable()->after('rule_snapshot')->index();
        });

        $schema->table('tabulation_finalization_runs', function (Blueprint $table): void {
            $table->char('dataset_hash', 64)->nullable()->after('source_snapshot')->index();
        });

        $schema->table('tabulation_processing_states', function (Blueprint $table): void {
            $table->char('dataset_hash', 64)->nullable()->after('source_snapshot')->index();
        });
    }

    public function down(): void
    {
        $schema = Schema::connection('exam');

        $schema->table('tabulation_processing_states', fn (Blueprint $table) => $table->dropColumn('dataset_hash'));
        $schema->table('tabulation_finalization_runs', fn (Blueprint $table) => $table->dropColumn('dataset_hash'));
        $schema->table('tabulation_processing_runs', fn (Blueprint $table) => $table->dropColumn('dataset_hash'));
        $schema->table('tabulation_results', function (Blueprint $table): void {
            $table->dropColumn(['cadre_category', 'birth_date']);
        });
    }
};
