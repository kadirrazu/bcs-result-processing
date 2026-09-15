<?php
namespace Tests\Feature\Merit;
use Tests\TestCase;
final class MeritRankingContractTest extends TestCase
{
 public function test_ranking_contract_contains_locked_tie_breaks_and_common_track_rule():void{$s=file_get_contents(app_path('Services/Merit/MeritRankingService.php'));foreach(['general_grand_total','technical_grand_total','preliminary_mark','birth_date','graduation_year','reg'] as $x)$this->assertStringContainsString($x,$s);foreach(["['GG','GN']","['TT','T']"] as $x)$this->assertStringContainsString($x,$s);$this->assertStringContainsString('businessTieGroups',$s);$this->assertStringContainsString('businessTieKey',$s);}
 public function test_cadre_ranking_is_technical_only_and_choice_independent():void{$s=file_get_contents(app_path('Services/Merit/MeritGenerationService.php'));$this->assertStringNotContainsString('ChoiceValidationResult::query()',$s);$this->assertStringContainsString('CircularEntry::query()',$s);$this->assertStringContainsString("whereNotNull('technical_merit_position')",$s);$this->assertStringContainsString('TECHNICAL_MERIT_AND_CIRCULAR_ELIGIBILITY',$s);$this->assertStringContainsString("'cadre_type'=>'TT'",$s);$this->assertStringContainsString('all_merit_tech',$s);}
}
