<?php
namespace Tests\Feature\Tabulation;
use Tests\TestCase;
final class TabulationReportingContractTest extends TestCase
{
 public function test_individual_finalized_view_separates_source_and_derived_sections_and_supports_pdf():void
 {
  $view=file_get_contents(resource_path('views/tabulation/show.blade.php'));$routes=file_get_contents(base_path('routes/tabulation.php'));
  self::assertStringContainsString('Upstream Finalized Data',$view);self::assertStringContainsString('Derived Tabulation Data',$view);self::assertStringContainsString("/results/{result}/pdf",$routes);
 }
 public function test_finalized_all_tabulations_xlsx_export_exists():void
 {
  $routes=file_get_contents(base_path('routes/tabulation.php'));$controller=file_get_contents(app_path('Http/Controllers/TabulationController.php'));
  self::assertStringContainsString('/results/export/xlsx',$routes);self::assertStringContainsString('AdministrativeXlsxExportService',$controller);
 }
 public function test_default_grand_total_review_warning_is_75_percent_and_config_driven():void
 {
  $config=require config_path('tabulation.php');self::assertSame(75.0,$config['grand_total_review_percent']);
  $generation=file_get_contents(app_path('Services/Tabulation/TabulationGenerationService.php'));self::assertStringContainsString("grand_total_review_percent",$generation);
 }
}
