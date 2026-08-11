<?php
namespace Tests\Feature\ChoiceValidation;
use PHPUnit\Framework\Attributes\Test;use Tests\TestCase;
final class ChoiceValidationCv3Cv4ContractTest extends TestCase {
 #[Test] public function processing_migration_uses_short_mysql_constraint_names():void{$p=base_path('database/examination-migrations/2026_08_12_001500_create_choice_validation_processing.php');$s=file_get_contents($p);$this->assertStringContainsString('cv_results_source_fk',$s);$this->assertStringContainsString('cv_items_result_fk',$s);}
 #[Test] public function engine_uses_registration_prs_and_finalized_circular_contract():void{$s=file_get_contents(app_path('Services/ChoiceValidation/ChoiceValidationEngine.php'));$this->assertStringContainsString('post_related_subject_code',$s);$p=file_get_contents(app_path('Services/ChoiceValidation/ChoiceValidationProcessingService.php'));$this->assertStringContainsString('CircularFinalizedDatasetService',$p);}
 #[Test] public function routes_expose_processing_and_review():void{$s=file_get_contents(base_path('routes/choice-validation.php'));$this->assertStringContainsString("name('process')",$s);$this->assertStringContainsString("name('results')",$s);$this->assertStringContainsString("name('result.detail')",$s);}
}
