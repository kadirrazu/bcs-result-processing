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

        self::assertStringContainsString('$row[\'context\'] = isset($row[\'context\'])', $service);
        self::assertStringContainsString('json_encode($row[\'context\']', $service);
        self::assertStringContainsString('Query Builder bulk insert() bypasses Eloquent casts', $service);
        self::assertStringContainsString("Do NOT use PHP's array-union (+)", $service);

        // Array-union is safe for ordinary result/ledger rows, but not for decision
        // event rows because event context must be serialized before Query Builder insert.
        $start = strpos($service, 'foreach (array_chunk($solution[\'events\'], 1000) as $chunk)');
        $end = $start === false ? false : strpos($service, 'AllocationDecisionEvent::query()->insert($rows);', $start);

        self::assertNotFalse($start);
        self::assertNotFalse($end);

        $eventBlock = substr($service, (int) $start, (int) $end - (int) $start);
        self::assertStringNotContainsString('$row + [', $eventBlock);
    }
}
