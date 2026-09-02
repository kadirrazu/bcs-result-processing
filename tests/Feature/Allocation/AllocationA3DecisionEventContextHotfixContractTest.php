<?php

namespace Tests\Feature\Allocation;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AllocationA3DecisionEventContextHotfixContractTest extends TestCase
{
    #[Test]
    public function decision_event_context_is_serialized_before_query_builder_bulk_insert(): void
    {
        $service = file_get_contents(app_path('Services/Allocation/AllocationPhaseOneService.php'));

        self::assertStringContainsString("\$row['context'] = isset(\$row['context'])", $service);
        self::assertStringContainsString("json_encode(\$row['context']", $service);
        self::assertStringContainsString('Query Builder bulk insert() bypasses Eloquent casts', $service);
        self::assertStringContainsString("Do NOT use PHP's array-union (+)", $service);

        // Regression guard: the old array-union form kept the raw context array
        // because left-hand keys take precedence in PHP's + operator.
        self::assertStringNotContainsString("\$row + [\n                    'allocation_run_id'", $service);
    }
}
