<?php

namespace Tests\Feature\Written;

use PHPUnit\Framework\TestCase;

final class WrittenRegistrationStatusAndDispositionContractTest extends TestCase
{
    public function test_written_processing_and_publication_respect_registration_status_and_disposition_workflow(): void
    {
        $root = dirname(__DIR__, 3);
        $rules = file_get_contents($root.'/app/Services/Written/WrittenRuleProcessingService.php');
        $final = file_get_contents($root.'/app/Services/Written/WrittenFinalizationService.php');
        $controller = file_get_contents($root.'/app/Http/Controllers/WrittenController.php');
        $routes = file_get_contents($root.'/routes/written.php');
        $view = file_get_contents($root.'/resources/views/written/dispositions.blade.php');

        self::assertStringContainsString("registrationStatuses", $rules);
        self::assertStringContainsString("registration_status_at_processing", $rules);
        self::assertStringContainsString("assertRegistrationEligibilityIsCurrent", $final);
        self::assertStringContainsString("join('registrations as r'", $controller);
        self::assertStringContainsString("where('r.status', 'active')", $controller);
        self::assertStringContainsString("/dispositions", $routes);
        self::assertStringContainsString("dispositionsXlsx", $routes);
        self::assertStringContainsString("dispositionsCsv", $routes);
        self::assertStringContainsString('Written Candidate Disposition', $view);
        self::assertStringContainsString('Mandatory reason', $view);
    }
}
