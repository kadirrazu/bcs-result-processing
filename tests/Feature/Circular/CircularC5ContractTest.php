<?php

namespace Tests\Feature\Circular;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class CircularC5ContractTest extends TestCase
{
    #[Test]
    public function c5_authority_workflow_contract_files_are_present(): void
    {
        $this->assertFileExists(app_path('Services/Circular/CircularAuthorityWorkflowService.php'));
        $this->assertFileExists(app_path('Reports/Pdf/CircularAuthorityPreviewPdfReport.php'));
        $this->assertFileExists(app_path('Models/CircularAuthorityPreview.php'));
        $this->assertFileExists(app_path('Models/CircularConfirmation.php'));
        $this->assertFileExists(resource_path('views/circular/authority.blade.php'));
        $this->assertFileExists(resource_path('views/reports/pdf/circular-authority-preview.blade.php'));
    }

    #[Test]
    public function circular_routes_expose_preview_confirmation_and_finalization(): void
    {
        $routes = file_get_contents(base_path('routes/circular.php'));
        $this->assertStringContainsString("name('authority.generate')", $routes);
        $this->assertStringContainsString("name('authority.confirm')", $routes);
        $this->assertStringContainsString("name('authority.finalize')", $routes);
    }

    #[Test]
    public function authority_service_hash_binds_preview_to_exact_dataset(): void
    {
        $service = file_get_contents(app_path('Services/Circular/CircularAuthorityWorkflowService.php'));
        $this->assertStringContainsString("hash('sha256'", $service);
        $this->assertStringContainsString('datasetHash', $service);
        $this->assertStringContainsString('CircularProcessingStatus::Confirmed', $service);
        $this->assertStringContainsString('CircularProcessingStatus::Finalized', $service);
    }
}
