<?php
namespace Tests\Feature\NonCadre;
use Tests\TestCase;
final class NonCadreChoiceNc3ContractTest extends TestCase
{
 public function test_nc3_is_dynamic_isolated_and_preserves_population_contract():void
 {
  $service=file_get_contents(app_path('Services/NonCadre/Choice/NonCadreChoiceService.php'));
  $routes=file_get_contents(base_path('routes/non-cadre.php'));
  $config=file_get_contents(config_path('non-cadre.php'));
  self::assertStringContainsString("'max_options' => (int) env('NON_CADRE_MAX_CHOICES', 20)",$config);
  self::assertStringNotContainsString("'mq_percent'",$config);
  self::assertStringContainsString("'opt'.str_pad",$service);
  self::assertStringContainsString("where('user_id', \$user)->where('reg', \$reg)",$service);
  self::assertStringContainsString("where('common_merit_eligible', true)",$service);
  self::assertStringContainsString('CADRE_ALLOCATED_HISTORICAL_ONLY',$service);
  self::assertStringContainsString('EMPTY_CHOICE',$service);
  self::assertStringContainsString('ALLOCATION_ELIGIBLE',$service);
  self::assertStringContainsString('BACHELOR_SUBJECT_MISMATCH',$service);
  self::assertStringContainsString('original_choices',$service);
  self::assertStringContainsString('validated_choices',$service);
  self::assertStringContainsString('effective_choices',$service);
  self::assertStringContainsString("prefix('choice')",$routes);
  self::assertStringNotContainsString('Services\\ChoiceValidation', $service);
 }
 public function test_nc3_manual_adjustment_stales_only_non_cadre_downstream():void
 {
  $service=file_get_contents(app_path('Services/NonCadre/Choice/NonCadreChoiceService.php'));
  self::assertStringContainsString("table('non_cadre_allocation_runs')",$service);
  self::assertStringContainsString("'allocation_status'=>'stale'",str_replace(' ', '', $service));
  self::assertStringContainsString("'reporting_status'=>'stale'",str_replace(' ', '', $service));
  self::assertStringNotContainsString("table('allocation_a5_runs')->update",$service);
 }
}
