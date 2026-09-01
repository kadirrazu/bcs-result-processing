<?php

namespace Tests\Feature\Allocation;

use PHPUnit\Framework\TestCase;

class AllocationA1SeatBreakupDuplicateSerialSchemaHotfixContractTest extends TestCase
{
    public function test_foundation_does_not_make_serial_unique_per_version(): void
    {
        $path = database_path('examination-migrations/2026_09_01_210000_create_allocation_foundation.php');
        $source = file_get_contents($path);

        $this->assertStringNotContainsString(
            "unique(['seat_breakup_version_id', 'sl'], 'alloc_seat_ver_sl_uq')",
            $source
        );

        $this->assertStringContainsString(
            "unique(['seat_breakup_version_id', 'circular_entry_id'], 'alloc_seat_ver_entry_uq')",
            $source
        );
    }

    public function test_corrective_migration_drops_legacy_serial_unique_index(): void
    {
        $path = database_path('examination-migrations/2026_09_01_232000_fix_allocation_seat_breakup_row_identity.php');
        $source = file_get_contents($path);

        $this->assertStringContainsString('alloc_seat_ver_sl_uq', $source);
        $this->assertStringContainsString('DROP INDEX', $source);
        $this->assertStringContainsString('information_schema.statistics', $source);
    }
}
