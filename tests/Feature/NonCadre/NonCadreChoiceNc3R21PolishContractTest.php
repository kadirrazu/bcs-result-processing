<?php

namespace Tests\Feature\NonCadre;

use Tests\TestCase;

final class NonCadreChoiceNc3R21PolishContractTest extends TestCase
{
    public function test_candidate_listing_uses_four_row_group_and_allocation_ready_choice(): void
    {
        $view = file_get_contents(resource_path('views/non-cadre/choice/version.blade.php'));
        self::assertStringContainsString('Original Choice', $view);
        self::assertStringContainsString('Validated Choice', $view);
        self::assertStringContainsString('Allocation Ready Choice', $view);
        self::assertStringContainsString('Details', $view);
        self::assertStringNotContainsString('Final Effective Choice', $view);
    }

    public function test_choice_lane_is_position_and_code_only(): void
    {
        $lane = file_get_contents(resource_path('views/non-cadre/choice/partials/choice-lane.blade.php'));
        self::assertStringContainsString("str_pad", $lane);
        self::assertStringNotContainsString('post_title', $lane);
    }

    public function test_filters_use_json_population_status_and_current_adjustment_state(): void
    {
        $service = file_get_contents(app_path('Services/NonCadre/Choice/NonCadreChoiceService.php'));
        self::assertStringContainsString("JSON_UNQUOTE(JSON_EXTRACT(i.validation_summary, '$.population_status')) = ?", $service);
        self::assertStringContainsString("whereColumn('i.validated_choices','<>','i.effective_choices')", $service);
        self::assertStringContainsString('paginate(20)->withQueryString()', $service);
    }

    public function test_detail_page_exposes_eligibility_context_and_allocation_ready_authority(): void
    {
        $view = file_get_contents(resource_path('views/non-cadre/choice/show.blade.php'));
        self::assertStringContainsString('Written Passed', $view);
        self::assertStringContainsString('Viva Passed', $view);
        self::assertStringContainsString('No Cadre Allocation', $view);
        self::assertStringContainsString('Allocation Ready Choice', $view);
        self::assertStringContainsString('authoritative choice list used by NC4 Allocation', $view);
        self::assertStringNotContainsString('<th>Post Name</th>', $view);
    }
}
