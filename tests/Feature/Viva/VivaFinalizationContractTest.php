<?php
namespace Tests\Feature\Viva;
use Tests\TestCase;
final class VivaFinalizationContractTest extends TestCase
{
 public function test_finalization_is_confidential_versioned_and_stale_aware():void{
  $service=file_get_contents(app_path('Services/Viva/VivaFinalizationService.php'));
  $routes=file_get_contents(base_path('routes/viva.php'));
  $view=file_get_contents(resource_path('views/viva/final-review.blade.php'));
  $reset=config('development-module-reset.modules.viva.tables');
  $this->assertStringContainsString("confirmation!=='FINALIZE'",$service);
  $this->assertStringContainsString('result_processed_at',$service);
  $this->assertStringContainsString('is_stale',$service);
  $this->assertStringContainsString('VIVA_RESULT_FINALIZED',$service);
  $this->assertStringContainsString("'status'=>'superseded'",$service);
  $this->assertStringContainsString('/final-review',$routes);
  $this->assertStringContainsString('Confidential administrative result',$view);
  $this->assertContains('viva_finalization_runs',$reset);
 }
}
