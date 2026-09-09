<?php

namespace Tests\Feature\Reporting;

use Tests\TestCase;

final class CadreWiseVerificationListingPagesContractTest extends TestCase
{
    public function test_main_landing_links_to_clean_separate_general_and_technical_listing_pages(): void
    {
        $landing = file_get_contents(resource_path('views/reporting/cadre-section/index.blade.php'));
        $listing = file_get_contents(resource_path('views/reporting/cadre-section/cadre-wise-index.blade.php'));
        $routes = file_get_contents(base_path('routes/reporting.php'));
        $controller = file_get_contents(app_path('Http/Controllers/Reporting/CadreSectionReportingController.php'));

        self::assertStringContainsString('cadre.verification.general-cadre-wise', $landing);
        self::assertStringContainsString('cadre.verification.technical-cadre-wise', $landing);

        self::assertStringNotContainsString('general-cadre-search', $landing);
        self::assertStringNotContainsString('technical-cadre-search', $landing);
        self::assertStringNotContainsString('general-cadre-report-row', $landing);
        self::assertStringNotContainsString('technical-cadre-report-row', $landing);

        self::assertStringContainsString('/verification/general-cadre-wise', $routes);
        self::assertStringContainsString('/verification/technical-cadre-wise', $routes);
        self::assertStringContainsString('public function generalCadreWiseIndex(', $controller);
        self::assertStringContainsString('public function technicalCadreWiseIndex(', $controller);

        self::assertStringContainsString('Search by Cadre Code or Abbreviation', $listing);
        self::assertStringContainsString('id="cadre-filter"', $listing);
        self::assertStringContainsString('class="cadre-report-row"', $listing);
        self::assertStringContainsString("if($kind === 'general')", $listing);
        self::assertStringContainsString('cadre.verification.general-cadre', $listing);
        self::assertStringContainsString('cadre.verification.technical', $listing);
    }
}
