<?php
namespace Tests\Feature\Merit;
use Tests\TestCase;
final class MeritRankingContractTest extends TestCase
{
 public function test_ranking_contract_contains_locked_tie_breaks_and_common_track_rule():void{$s=file_get_contents(app_path('Services/Merit/MeritRankingService.php'));foreach(['general_grand_total','technical_grand_total','preliminary_mark','birth_date','graduation_year','reg'] as $x)$this->assertStringContainsString($x,$s);foreach(["['GG','GN']","['TT','T']"] as $x)$this->assertStringContainsString($x,$s);}
 public function test_cadre_ranking_is_from_validated_choice_and_circular_and_source_merit():void{$s=file_get_contents(app_path('Services/Merit/MeritGenerationService.php'));$this->assertStringContainsString('ChoiceValidationResult::query()',$s);$this->assertStringContainsString('CircularEntry::query()',$s);$this->assertStringContainsString('$type===\'GG\'?$m->general_merit_position:$m->technical_merit_position',$s);$this->assertStringContainsString('VALIDATED_CHOICE_AND_CIRCULAR_ELIGIBILITY',$s);$this->assertStringContainsString('all_merit_tech',$s);}
}
