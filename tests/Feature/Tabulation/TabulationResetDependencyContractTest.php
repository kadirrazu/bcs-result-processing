<?php
namespace Tests\Feature\Tabulation;
use Tests\TestCase;
final class TabulationResetDependencyContractTest extends TestCase
{
 public function test_choice_validation_no_longer_claims_tabulation_as_downstream():void
 {
  $config=require config_path('development-module-reset.php');self::assertNotContains('Tabulation, Merit, Choice Optimization and Allocation',$config['modules']['choice_validation']['downstream']);self::assertSame(['Merit, Choice Optimization and Allocation'],$config['modules']['choice_validation']['downstream']);
 }
 public function test_tabulation_reset_registry_owns_all_tabulation_tables():void
 {
  $config=require config_path('development-module-reset.php');self::assertSame(['tabulation_processing_audits','tabulation_finalization_runs','tabulation_results','tabulation_processing_runs','tabulation_processing_states'],$config['modules']['tabulation']['tables']);
 }
}
