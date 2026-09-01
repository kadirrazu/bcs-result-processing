<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::connection('exam')->hasTable('allocation_seat_breakup_rows')) {
            return;
        }

        $connection = DB::connection('exam');
        $database = $connection->getDatabaseName();

        $exists = $connection->table('information_schema.statistics')
            ->where('table_schema', $database)
            ->where('table_name', 'allocation_seat_breakup_rows')
            ->where('index_name', 'alloc_seat_ver_sl_uq')
            ->exists();

        if ($exists) {
            // Circular serials are display/order identifiers and are not globally
            // unique across sections. Row identity is the Circular entry itself.
            $connection->statement(
                'ALTER TABLE `allocation_seat_breakup_rows` DROP INDEX `alloc_seat_ver_sl_uq`'
            );
        }
    }

    public function down(): void
    {
        // Intentionally do not restore alloc_seat_ver_sl_uq.
        // Duplicate serials within a Seat Breakup version are valid when they
        // belong to different authoritative Circular entries. Re-introducing
        // this constraint would violate the corrected business/data contract.
    }
};
