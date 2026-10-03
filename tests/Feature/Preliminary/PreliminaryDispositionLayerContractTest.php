<?php

namespace Tests\Feature\Preliminary;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PreliminaryDispositionLayerContractTest extends TestCase
{
    #[Test]
    public function preliminary_has_optional_disposition_management_and_publication_exclusion_contract(): void
    {
        $routes = file_get_contents(base_path('routes/preliminary.php'));
        $controller = file_get_contents(app_path('Http/Controllers/PreliminaryController.php'));
        $service = file_get_contents(app_path('Services/Preliminary/PreliminaryDispositionService.php'));
        $finalization = file_get_contents(app_path('Services/Preliminary/PreliminaryFinalizationService.php'));
        $view = file_get_contents(resource_path('views/preliminary/dispositions.blade.php'));

        self::assertStringContainsString("->name('dispositions')", $routes);
        self::assertStringContainsString("->name('dispositions.xlsx')", $routes);
        self::assertStringContainsString("->name('dispositions.csv')", $routes);
        self::assertStringContainsString("'rows' => \$this->dispositionQuery(\$status, \$search, true)", $controller);
        self::assertStringContainsString('$allowActiveSearch = $includeActiveSearch', $controller);
        self::assertStringContainsString("whereIn('p.candidate_status',['cancelled','withheld'])", str_replace(' ', '', $controller));
        self::assertStringContainsString('All / Search Active Candidate', $view);
        self::assertStringContainsString('PRELIMINARY_CANDIDATE_DISPOSITION_CHANGED', $service);
        self::assertStringContainsString("where('p.candidate_status', 'active')", $finalization);
        self::assertStringContainsString('Export XLSX', $view);
        self::assertStringContainsString('Mandatory reason', $view);
    }
}
