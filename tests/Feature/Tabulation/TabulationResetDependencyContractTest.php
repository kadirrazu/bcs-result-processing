<?php
namespace Tests\Feature\Tabulation;
use Tests\TestCase;
final class TabulationResetDependencyContractTest extends TestCase
{
 public function test_choice_validation_reset_no_longer_claims_merit_as_downstream():void
 {
  $config=require config_path('development-module-reset.php');self::assertSame(['Choice Optimization and Allocation'],$config['modules']['choice_validation']['downstream']);
 }
 public function test_merit_reset_warns_only_allocation_as_downstream():void
 {
  $config=require config_path('development-module-reset.php');self::assertSame(['Allocation'],$config['modules']['merit']['downstream']);
 }
 public function test_circular_reset_warns_all_true_downstream_consumers():void
 {
  $config=require config_path('development-module-reset.php');self::assertSame(['Merit, Choice Validation, Choice Optimization and Allocation'],$config['modules']['circular']['downstream']);
 }
 public function test_tabulation_reset_registry_owns_all_tabulation_tables():void
 {
  $config=require config_path('development-module-reset.php');self::assertSame(['tabulation_processing_audits','tabulation_finalization_runs','tabulation_results','tabulation_processing_runs','tabulation_processing_states'],$config['modules']['tabulation']['tables']);
 }
}
