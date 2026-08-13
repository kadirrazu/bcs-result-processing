<?php
namespace Tests\Feature\Merit;
use Tests\TestCase;
final class MeritTabulationGraduationYearContractTest extends TestCase
{
 public function test_graduation_year_is_optional_snapshot_fallback_for_merit_ranking():void
 {
  $migration=file_get_contents(database_path('examination-migrations/2026_08_14_004500_add_graduation_year_to_tabulation_results.php'));
  $generation=file_get_contents(app_path('Services/Tabulation/TabulationGenerationService.php'));
  $hasher=file_get_contents(app_path('Services/Tabulation/TabulationDatasetHasher.php'));
  $ranking=file_get_contents(app_path('Services/Merit/MeritRankingService.php'));

  $this->assertStringContainsString("'graduation_year'",$migration);
  $this->assertStringContainsString("'r.graduation_year'",$generation);
  $this->assertStringContainsString("'graduation_year'=>\$row->graduation_year",str_replace(' ','',$generation));
  $this->assertStringContainsString("'graduation_year' =>",$hasher);
  $this->assertStringContainsString('$a->graduation_year===null?PHP_INT_MAX',str_replace(' ','',$ranking));
  $this->assertStringContainsString('$b->graduation_year===null?PHP_INT_MAX',str_replace(' ','',$ranking));
  $this->assertStringContainsString('$areg=(string)$a->reg',str_replace(' ','',$ranking));
 }
}
