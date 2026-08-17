<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::connection('exam')->hasTable('choice_validation_results')) {
            return;
        }

        DB::connection('exam')->statement(
            "ALTER TABLE `choice_validation_results` MODIFY `status` VARCHAR(80) NOT NULL"
        );
    }

    public function down(): void
    {
        if (! Schema::connection('exam')->hasTable('choice_validation_results')) {
            return;
        }

        $hasLongStatus = DB::connection('exam')
            ->table('choice_validation_results')
            ->whereRaw('CHAR_LENGTH(`status`) > 32')
            ->exists();

        if ($hasLongStatus) {
            throw new RuntimeException(
                'Cannot shrink choice_validation_results.status to VARCHAR(32) while longer statuses exist.'
            );
        }

        DB::connection('exam')->statement(
            "ALTER TABLE `choice_validation_results` MODIFY `status` VARCHAR(32) NOT NULL"
        );
    }
};
