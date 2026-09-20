<?php

namespace Tests\Feature\Allocation;

use Tests\TestCase;

class AllocationSequentialDisplayNumberingContractTest extends TestCase
{
    public function test_allocation_uses_clean_sequential_display_numbering_without_renaming_internal_workflow(): void
    {
        $index = file_get_contents(resource_path('views/allocation/index.blade.php'));

        foreach ([
            'A1 — Allocation Settings',
            'A2 — Seat Breakup',
            'A3 — Frozen Allocation Input & Deterministic Queues',
            'A4 — Phase-1 MQ + Quota Allocation',
            'A5 — Phase-2 NM + Shifting',
            'A6 — Integrity & Finalization',
            'A7 — Result Disposition / Publication Control',
            'A8 — Allocation Reporting &amp; Export',
        ] as $label) {
            self::assertStringContainsString($label, $index);
        }

        self::assertStringNotContainsString('A5.5 —', $index);

        // Internal routes/services remain stable: this is presentation numbering only.
        $routes = file_get_contents(base_path('routes/allocation.php'));
        self::assertStringContainsString("name('a5.", $routes);
        self::assertStringContainsString("name('a6.", $routes);
        self::assertStringContainsString("/a5-5", $routes);
        self::assertFileExists(app_path('Services/Allocation/AllocationA6ReadinessService.php'));
    }
}
