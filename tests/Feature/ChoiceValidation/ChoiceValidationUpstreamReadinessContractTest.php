<?php

namespace Tests\Feature\ChoiceValidation;

use Tests\TestCase;

final class ChoiceValidationUpstreamReadinessContractTest extends TestCase
{
    public function test_landing_and_process_share_strict_upstream_readiness_gate(): void
    {
        $service = file_get_contents(app_path('Services/ChoiceValidation/ChoiceValidationReadinessService.php'));
        $controller = file_get_contents(app_path('Http/Controllers/ChoiceValidationController.php'));
        $view = file_get_contents(resource_path('views/choice-validation/index.blade.php'));

        $this->assertStringContainsString('verifiedSummary()', $service);
        $this->assertStringContainsString('VivaProcessingStatus::ResultFinalized', $service);
        $this->assertStringContainsString("where('status', 'current')", $service);
        $this->assertStringContainsString("'label' => 'Approved Choice Source'", $service);
        $this->assertStringContainsString('public function assertReady(): void', $service);

        $this->assertStringContainsString('ChoiceValidationReadinessService $readiness', $controller);
        $this->assertStringContainsString('$validationReadiness = $readiness->summary();', $controller);
        $this->assertStringContainsString('$readiness->assertReady();', $controller);

        $this->assertStringContainsString('Upstream Readiness / Validation Preconditions', $view);
        $this->assertStringContainsString('VALIDATION BLOCKED', $view);
        $this->assertStringContainsString('@disabled(!$validationCanRun)', $view);
        $this->assertStringContainsString('Validation cannot start', $view);
    }
}
