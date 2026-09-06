<?php

namespace Tests\Feature\Allocation;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AllocationA4LandingProgressAlignmentContractTest extends TestCase
{
    #[Test]
    public function a4_landing_percent_value_and_percent_sign_are_kept_in_one_flex_item(): void
    {
        $view = file_get_contents(resource_path('views/allocation/index.blade.php'));

        self::assertStringContainsString(
            '<span class="text-secondary text-nowrap"><span id="a4-landing-percent">{{ (int)$latestA4->progress_percent }}</span>%</span>',
            $view
        );

        self::assertStringNotContainsString(
            '<span id="a4-landing-percent">{{ (int)$latestA4->progress_percent }}</span>%' . PHP_EOL,
            $view
        );
    }
}
