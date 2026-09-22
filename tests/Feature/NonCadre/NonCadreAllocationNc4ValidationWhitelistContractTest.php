<?php

namespace Tests\Feature\NonCadre;

use Tests\TestCase;

class NonCadreAllocationNc4ValidationWhitelistContractTest extends TestCase
{
    public function test_nc4_critical_validation_whitelist_is_enforced(): void
    {
        $source = file_get_contents(app_path('Services/NonCadre/Allocation/NonCadreAllocationService.php'));

        foreach ([
            'NO_CADRE_ALLOCATED_CANDIDATE',
            'ALLOCATION_READY_CHOICE',
            'BACHELOR_SUBJECT_ELIGIBILITY',
            'COMMON_MERIT_INTEGRITY',
            'UNIQUE_CANDIDATE',
            'SEAT_CONSERVATION',
        ] as $check) {
            $this->assertStringContainsString("'{$check}'", $source);
        }

        $this->assertStringContainsString("whereIn('check_code',\$required)", $source);
        $this->assertStringContainsString('critical validation whitelist is incomplete', $source);
    }
}
