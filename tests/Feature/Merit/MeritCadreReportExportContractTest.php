<?php

namespace Tests\Feature\Merit;

use Tests\TestCase;

final class MeritCadreReportExportContractTest extends TestCase
{
    public function test_all_merit_and_individual_cadre_xlsx_exports_are_finalized_only_and_streamed(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/MeritController.php'));
        $cadre = file_get_contents(resource_path('views/merit/cadre.blade.php'));

        $this->assertStringContainsString('currentFinalizedRun()', $controller);
        $this->assertStringContainsString('function exportAll', $controller);
        $this->assertStringContainsString('function exportCadre', $controller);
        $this->assertStringContainsString('->cursor()', $controller);
        $this->assertStringContainsString("'Dataset Hash' => \$state->dataset_hash", $controller);
        $this->assertStringContainsString("route('merit.cadre.export.xlsx'", $cadre);
    }
}
