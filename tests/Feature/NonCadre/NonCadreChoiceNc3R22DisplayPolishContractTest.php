<?php

namespace Tests\Feature\NonCadre;

use Tests\TestCase;

final class NonCadreChoiceNc3R22DisplayPolishContractTest extends TestCase
{
    public function test_dataset_choice_rows_use_full_width_label_then_lane(): void
    {
        $view = file_get_contents(resource_path('views/non-cadre/choice/version.blade.php'));

        self::assertStringContainsString('Common Merit: <strong class="fw-bold">', $view);
        self::assertStringContainsString('<td colspan="2" class="px-3 py-2"><div class="fw-bold mb-2">Original Choice</div>', $view);
        self::assertStringContainsString('<td colspan="2" class="px-3 py-2"><div class="fw-bold mb-2">Validated Choice</div>', $view);
        self::assertStringContainsString('<td colspan="2" class="px-3 py-2"><div class="fw-bold mb-2">Allocation Ready Choice</div>', $view);
    }

    public function test_shared_choice_lane_places_position_above_post_code(): void
    {
        $lane = file_get_contents(resource_path('views/non-cadre/choice/partials/choice-lane.blade.php'));

        self::assertStringContainsString('flex-column align-items-center', $lane);
        self::assertStringContainsString("str_pad((string)(\$i+1),2,'0',STR_PAD_LEFT)", $lane);
        self::assertStringContainsString('<span class="fw-bold mt-1">{{ $code }}</span>', $lane);
        self::assertStringNotContainsString('post_title', $lane);
    }
}
