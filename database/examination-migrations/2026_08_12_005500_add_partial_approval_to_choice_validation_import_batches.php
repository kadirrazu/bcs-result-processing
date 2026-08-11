<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('exam')->table('choice_validation_import_batches', function (Blueprint $table): void {
            $table->unsignedInteger('source_version')->nullable()->after('approved_rows')->index('cv_import_batch_src_ver_idx');
        });

        // Preserve compatibility with any source batch approved before this patch.
        DB::connection('exam')->table('choice_validation_import_batches')
            ->whereNull('source_version')
            ->orderBy('id')
            ->get(['id'])
            ->each(function (object $batch): void {
                $version = DB::connection('exam')->table('choice_validation_sources')
                    ->where('source_batch_id', $batch->id)
                    ->max('source_version');
                if ($version !== null) {
                    DB::connection('exam')->table('choice_validation_import_batches')
                        ->where('id', $batch->id)
                        ->update(['source_version' => (int) $version]);
                }
            });
    }

    public function down(): void
    {
        Schema::connection('exam')->table('choice_validation_import_batches', function (Blueprint $table): void {
            $table->dropIndex('cv_import_batch_src_ver_idx');
            $table->dropColumn('source_version');
        });
    }
};
