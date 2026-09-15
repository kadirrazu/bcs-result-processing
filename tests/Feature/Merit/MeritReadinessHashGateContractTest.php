<?php
namespace Tests\Feature\Merit;
use Tests\TestCase;
final class MeritReadinessHashGateContractTest extends TestCase
{
 public function test_merit_readiness_requires_only_hash_verified_circular_and_tabulation():void{$service=file_get_contents(app_path('Services/Merit/MeritReadinessService.php'));$circular=file_get_contents(app_path('Services/Circular/CircularFinalizedDatasetService.php'));$this->assertStringContainsString('CircularFinalizedDatasetService $circular',$service);$this->assertStringContainsString('TabulationFinalizedDatasetService $tabulation',$service);$this->assertStringNotContainsString('ChoiceValidationFinalizedDatasetService',$service);$this->assertStringContainsString("'dataset_hash'",$service);$this->assertStringContainsString('CIRCULAR_DATASET_HASH_MISMATCH',$circular);$this->assertStringContainsString('verifiedConfirmation()',$circular);}
 public function test_merit_readiness_does_not_consume_raw_academic_modules_directly():void{$service=file_get_contents(app_path('Services/Merit/MeritReadinessService.php'));foreach(['Registration','PreliminaryResult','WrittenResult','VivaResult'] as $rawModel)$this->assertStringNotContainsString("App\\Models\\{$rawModel}",$service);}
 public function test_choice_validation_does_not_stale_merit():void{$graph=file_get_contents(app_path('Services/Dependencies/DownstreamStalePropagationService.php'));$this->assertStringContainsString("'choice_validation' => ['choice_optimization']",$graph);}
}
