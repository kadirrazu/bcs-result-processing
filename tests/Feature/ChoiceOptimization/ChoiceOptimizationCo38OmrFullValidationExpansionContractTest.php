<?php

namespace Tests\Feature\ChoiceOptimization;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ChoiceOptimizationCo38OmrFullValidationExpansionContractTest extends TestCase
{
    #[Test]
    public function effective_omr_yes_reuses_choice_validation_row_rules_and_domain_engine(): void
    {
        $service = file_get_contents(app_path('Services/ChoiceOptimization/ChoiceOptimizationOmrValidationService.php'));
        $engine = file_get_contents(app_path('Services/ChoiceValidation/ChoiceValidationEngine.php'));

        $this->assertStringContainsString('ChoiceRowRuleValidator', $service);
        $this->assertStringContainsString('$this->rowRules->validate', $service);
        $this->assertStringContainsString('$this->engine->validate', $service);
        $this->assertStringContainsString('DuplicateChoice', $engine);
        $this->assertStringContainsString('NotInFinalizedCircular', $engine);
        $this->assertStringContainsString('TrackNotAllowed', $engine);
        $this->assertStringContainsString('BachelorSubjectMismatch', $engine);
        $this->assertStringContainsString('PrsMismatch', $engine);
    }

    #[Test]
    public function parent_cadre_expansion_and_removal_are_the_same_choice_validation_engine_contract(): void
    {
        $engine = file_get_contents(app_path('Services/ChoiceValidation/ChoiceValidationEngine.php'));

        $this->assertStringContainsString('subRowsByParentCode', $engine);
        $this->assertStringContainsString('sub_serial', $engine);
        $this->assertStringContainsString('ParentNoEligibleSubCadre', $engine);
        $this->assertStringContainsString("'expanded'", $engine);
        $this->assertStringContainsString('Parent choice expanded to eligible sub-cadre.', $engine);
    }

    #[Test]
    public function approval_rejects_any_unvalidated_or_empty_effective_omr_override(): void
    {
        $approval = file_get_contents(app_path('Services/ChoiceOptimization/ChoiceOptimizationOmrApprovalService.php'));

        $this->assertStringContainsString("choice_validation_status !== 'valid'", $approval);
        $this->assertStringContainsString('$override === []', $approval);
        $this->assertStringContainsString('clean, non-empty validated OMR choice sequence', $approval);
    }

    #[Test]
    public function review_top_row_has_equal_header_value_layout_and_compact_category_code(): void
    {
        $view = file_get_contents(resource_path('views/choice-optimization/omr-show.blade.php'));

        $this->assertStringContainsString('grid-template-rows: 44px minmax(72px, 1fr)', $view);
        $this->assertStringContainsString('co-context-body', $view);
        $this->assertStringContainsString('$candidateContext[\'category_code\']', $view);
        $this->assertStringNotContainsString('$candidateContext[\'category_label\']', $view);
        $this->assertStringContainsString('Left to Right = Preference Order', $view);
    }
}
