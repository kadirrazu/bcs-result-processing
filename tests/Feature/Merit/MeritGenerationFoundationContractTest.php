<?php
namespace Tests\Feature\Merit;
use Tests\TestCase;
final class MeritGenerationFoundationContractTest extends TestCase
{
 public function test_merit_is_queue_based_derived_and_has_no_import_route():void{$routes=file_get_contents(base_path('routes/merit.php'));$job=file_get_contents(app_path('Jobs/ProcessMeritGeneration.php'));$service=file_get_contents(app_path('Services/Merit/MeritGenerationService.php'));$this->assertStringContainsString("Route::post('/generate'",$routes);$this->assertStringNotContainsString('/import',$routes);$this->assertStringContainsString('implements ShouldQueue',$job);foreach(['Registration::','PreliminaryResult::','WrittenResult::','VivaResult::'] as $raw)$this->assertStringNotContainsString($raw,$service);$this->assertStringContainsString('TabulationResult::query()',$service);}
 public function test_merit_schema_has_all_rank_outputs_and_normalized_cadre_projection():void{$m=file_get_contents(database_path('examination-migrations/2026_08_14_005000_create_merit_generation_module.php'));foreach(['common_merit_position','general_merit_position','technical_merit_position','all_merit_tech','merit_cadre_ranks','cadre_merit_position','source_merit_position','choice_position'] as $x)$this->assertStringContainsString($x,$m);$this->assertStringContainsString("unique(['processing_run_id','cadre_code','registration_id']",$m);}
 public function test_merit_gate_uses_hash_verified_three_authorities():void{$r=file_get_contents(app_path('Services/Merit/MeritReadinessService.php'));foreach(['CircularFinalizedDatasetService','TabulationFinalizedDatasetService','ChoiceValidationFinalizedDatasetService','dataset_hash'] as $x)$this->assertStringContainsString($x,$r);}
}
