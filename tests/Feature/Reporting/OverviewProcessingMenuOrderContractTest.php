<?php

namespace Tests\Feature\Reporting;

use Tests\TestCase;

final class OverviewProcessingMenuOrderContractTest extends TestCase
{
    public function test_overview_module_status_follows_processing_menu_order(): void
    {
        $overview = file_get_contents(app_path('Services/Overview/ExaminationOverviewService.php'));

        $orderedModules = [
            "module('Registration', 'registrations.index'",
            "stateModule('Preliminary', 'preliminary.index'",
            "stateModule('Written', 'written.index'",
            "stateModule('Viva', 'viva.index'",
            "stateModule('Tabulation', 'tabulation.index'",
            "stateModule('Circular', 'circular.index'",
            "stateModule('Merit', 'merit.index'",
            "stateModule('Choice Validation', 'choice-validation.index'",
            "stateModule('Choice Optimization', 'choice-optimization.index'",
            "stateModule('Allocation', 'allocation.index'",
        ];

        $positions = array_map(static function (string $needle) use ($overview): int {
            $position = strpos($overview, $needle);
            self::assertNotFalse($position, "Overview module entry missing: {$needle}");

            return $position;
        }, $orderedModules);

        self::assertSame($positions, collect($positions)->sort()->values()->all());
    }
}
