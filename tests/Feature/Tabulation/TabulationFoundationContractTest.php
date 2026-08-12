<?php
namespace Tests\Feature\Tabulation;
use Tests\TestCase;
final class TabulationFoundationContractTest extends TestCase
{
 public function test_tabulation_has_no_import_route_and_uses_queue_generation():void
 {
  $routes=file_get_contents(base_path('routes/tabulation.php'));$job=file_get_contents(app_path('Jobs/ProcessTabulation.php'));$controller=file_get_contents(app_path('Http/Controllers/TabulationController.php'));
  self::assertStringNotContainsString('/import',$routes);self::assertStringContainsString("Route::post('/generate'",$routes);self::assertStringContainsString('implements ShouldQueue',$job);self::assertStringContainsString('ProcessTabulation::dispatch',$controller);
 }
 public function test_locked_upstream_gate_and_appeared_only_population_are_explicit():void
 {
  $readiness=file_get_contents(app_path('Services/Tabulation/TabulationReadinessService.php'));$generation=file_get_contents(app_path('Services/Tabulation/TabulationGenerationService.php'));
  foreach(['registration','preliminary','written','viva'] as $dependency)self::assertStringContainsString("'{$dependency}'",$readiness);
  self::assertStringContainsString("where('v.attendance_status','appeared')",$generation);self::assertStringContainsString("where('v.status','active')",$generation);
  self::assertStringNotContainsString('choice_validation',$readiness);self::assertStringNotContainsString('circular',$readiness);
 }
 public function test_one_row_per_candidate_per_run_is_enforced():void
 {
  $migration=file_get_contents(database_path('examination-migrations/2026_08_12_210000_create_tabulation_module_foundation.php'));
  self::assertStringContainsString("unique(['processing_run_id', 'registration_id'], 'tab_run_registration_uq')",$migration);
 }
 public function test_tabulation_results_are_derived_and_have_no_manual_edit_route():void
 {
  $routes=file_get_contents(base_path('routes/tabulation.php'));
  self::assertStringNotContainsString('edit',$routes);self::assertStringNotContainsString('update',$routes);self::assertStringNotContainsString('correction',$routes);
 }
}
