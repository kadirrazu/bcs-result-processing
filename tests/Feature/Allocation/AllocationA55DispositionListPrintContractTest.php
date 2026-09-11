<?php

namespace Tests\Feature\Allocation;

use Tests\TestCase;

final class AllocationA55DispositionListPrintContractTest extends TestCase
{
    public function test_withheld_and_cancelled_lists_support_reason_search_cadre_filter_and_print(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/AllocationDispositionController.php'));
        $routes = file_get_contents(base_path('routes/allocation.php'));
        $index = file_get_contents(resource_path('views/allocation/disposition/index.blade.php'));
        $list = file_get_contents(resource_path('views/allocation/disposition/list.blade.php'));

        self::assertStringContainsString('dispositionList', $controller);
        self::assertStringContainsString("AllocationResultDispositionService::WITHHELD", $controller);
        self::assertStringContainsString("AllocationResultDispositionService::CANCELLED", $controller);
        self::assertStringContainsString("d.reg", $controller);
        self::assertStringContainsString("r.name", $controller);
        self::assertStringContainsString("d.cadre_code", $controller);
        self::assertStringContainsString("d.reason", $controller);
        self::assertStringContainsString("'r.birth_date as candidate_birth_date'", $controller);

        $listMethodStart = strpos($controller, 'public function dispositionList');
        $showMethodStart = strpos($controller, 'public function show', $listMethodStart);
        $listMethod = substr($controller, $listMethodStart, $showMethodStart - $listMethodStart);

        self::assertStringContainsString('$statusCadreCodes = AllocationResultDisposition::query()', $listMethod);
        self::assertStringContainsString("->where('status', $status)", $listMethod);
        self::assertStringContainsString("->distinct()", $listMethod);
        self::assertStringNotContainsString('$reports->cadres($a5)', $listMethod);

        self::assertStringContainsString("/a5-5/list/{status}", $routes);
        self::assertStringContainsString("['WITHHELD','CANCELLED']", $routes);
        self::assertStringContainsString("View / Print List", $index);

        self::assertStringContainsString('Search by Reg / Name', $list);
        self::assertStringContainsString('Filter by Cadre', $list);
        foreach ([
            'Sl.',
            'Registration No.',
            'Name',
            'Date of Birth',
            'Cadre',
            'Merit Position',
            'Basis',
            'Status',
            'Reason',
        ] as $heading) {
            self::assertStringContainsString($heading, $list);
        }
        self::assertStringContainsString('candidate_birth_date', $list);
        self::assertStringContainsString('window.print()', $list);
        self::assertStringContainsString('@page{size:A4 landscape;margin:.5in}', $list);
    }
}
