<?php

namespace Tests\Feature\Allocation;

use App\Services\Allocation\AllocationA6ExcelFieldCatalog;
use Tests\TestCase;

final class AllocationA6ExcelCadreCompanionContractTest extends TestCase
{
    public function test_allocated_cadre_code_only_adds_cadre_abbreviation_as_automatic_companion(): void
    {
        $fields = app(AllocationA6ExcelFieldCatalog::class)->validateSelection([
            'allocation.cadre',
        ]);

        self::assertSame([
            'allocation.cadre',
            'allocation.cadre_abbr',
        ], $fields);

        self::assertNotContains('allocation.status', $fields);
        self::assertNotContains('allocation.withheld', $fields);
        self::assertNotContains('allocation.withheld_reason', $fields);
        self::assertNotContains('allocation.cancelled', $fields);
        self::assertNotContains('allocation.cancelled_reason', $fields);
    }

    public function test_disposition_fields_are_still_available_when_explicitly_selected(): void
    {
        $selected = [
            'allocation.status',
            'allocation.withheld',
            'allocation.withheld_reason',
            'allocation.cancelled',
            'allocation.cancelled_reason',
        ];

        self::assertSame(
            $selected,
            app(AllocationA6ExcelFieldCatalog::class)->validateSelection($selected)
        );
    }
}
