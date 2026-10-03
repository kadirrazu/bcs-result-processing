<?php
namespace Tests\Feature\ChoiceOptimization;
use Tests\TestCase;
final class ChoiceOptimizationHistoricalMatchedExportContractTest extends TestCase
{
    public function test_included_confirmed_previous_bcs_matches_have_xlsx_and_dbf_exports(): void
    {
        $service=file_get_contents(app_path('Services/ChoiceOptimization/ChoiceOptimizationHistoricalMatchExportService.php'));
        $routes=file_get_contents(base_path('routes/choice-optimization.php'));
        $view=file_get_contents(resource_path('views/choice-optimization/index.blade.php'));
        $this->assertStringContainsString("where('included_in_optimization', true)",$service);
        $this->assertStringContainsString("where('match_status', 'matched')",$service);
        $this->assertStringContainsString('user_id',$service);
        $this->assertStringContainsString('previous_bcs_match_history',$service);
        $this->assertStringContainsString("historical.export.matches.xlsx",$routes);
        $this->assertStringContainsString("historical.export.matches.dbf",$routes);
        $this->assertStringContainsString('Matched History XLSX',$view);
    }
}
