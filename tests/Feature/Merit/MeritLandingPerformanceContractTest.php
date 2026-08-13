<?php
namespace Tests\Feature\Merit;
use Tests\TestCase;
final class MeritLandingPerformanceContractTest extends TestCase
{
 public function test_landing_uses_lightweight_readiness_but_strict_work_gates_rehash():void
 {
  $readiness=file_get_contents(app_path('Services/Merit/MeritReadinessService.php'));
  $controller=file_get_contents(app_path('Http/Controllers/MeritController.php'));
  $run=file_get_contents(app_path('Services/Merit/MeritRunService.php'));
  $generation=file_get_contents(app_path('Services/Merit/MeritGenerationService.php'));
  $finalization=file_get_contents(app_path('Services/Merit/MeritFinalizationService.php'));

  $this->assertStringContainsString('storedFinalizedSummary()', $readiness);
  $this->assertStringContainsString('buildInspection(false)', $readiness);
  $this->assertStringContainsString('buildInspection(true)', $readiness);
  $this->assertStringContainsString('$readinessInspection=$readiness->inspect()', str_replace(' ','',$controller));
  $this->assertStringContainsString('$stale->synchronize($readinessInspection)', str_replace(' ','',$controller));
  $this->assertStringContainsString('$this->readiness->assertReady()', $run);
  $this->assertStringContainsString('$this->readiness->assertReady()', $generation);
  $this->assertStringContainsString('$this->readiness->assertReady()', $finalization);
 }
}
