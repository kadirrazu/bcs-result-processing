<?php

namespace Tests\Feature\Viva;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class VivaMappingApprovalPerformanceContractTest extends TestCase
{
    #[Test]
    public function mapping_approval_uses_chunk_lookup_and_bulk_upsert_instead_of_row_wise_writes(): void
    {
        $service = file_get_contents(app_path('Services/Viva/VivaMappingApprovalService.php'));

        self::assertStringContainsString("config('viva.mapping_merge_chunk_size'", $service);
        self::assertStringContainsString("->whereIn('registration_id', \$registrationIds)", $service);
        self::assertStringContainsString("->upsert(", $service);
        self::assertStringNotContainsString("->where('registration_id', \$row->registration_id)->first()", $service);
        self::assertStringNotContainsString("->where('id', \$old->id)->update(", $service);
    }

    #[Test]
    public function mapping_merge_chunk_size_is_configurable_and_positive(): void
    {
        self::assertGreaterThan(0, (int) config('viva.mapping_merge_chunk_size'));
    }
}
