<?php

namespace Tests\Feature\Viva;

use Tests\TestCase;

final class VivaManualCorrectionWorkflowTest extends TestCase
{
    public function test_manual_correction_contract_preserves_raw_source_and_requires_reason(): void
    {
        $service = file_get_contents(app_path('Services/Viva/VivaManualCorrectionService.php'));
        $routes = file_get_contents(base_path('routes/viva.php'));
        $edit = file_get_contents(resource_path('views/viva/edit.blade.php'));

        $this->assertStringContainsString("'reason' => 'A correction reason is required.'", $service);
        $this->assertStringContainsString("'VIVA_MANUAL_CORRECTION'", $service);
        $this->assertStringContainsString("'raw_source_preserved' => true", $service);
        $this->assertStringContainsString("'is_stale' => true", $service);
        $this->assertStringNotContainsString("'raw_mark' =>", $service);
        $this->assertStringContainsString("candidates/{result}/edit", $routes);
        $this->assertStringContainsString('Original Imported Source', $edit);
        $this->assertStringContainsString('Reason for correction', $edit);
    }
}
