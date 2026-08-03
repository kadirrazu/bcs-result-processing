<?php

namespace Tests\Unit\Viva;

use App\Services\Viva\VivaRuleConfig;
use Tests\TestCase;

final class VivaRuleConfigTest extends TestCase
{
    public function test_viva_thresholds_are_derived_from_configuration(): void
    {
        $rules = app(VivaRuleConfig::class);

        self::assertSame(100.0, $rules->fullMark());
        self::assertSame(50.0, $rules->passPercent());
        self::assertSame(50.0, $rules->passMark());
        self::assertSame(80.0, $rules->highMarkReviewPercent());
        self::assertSame(80.0, $rules->highMarkReviewMark());
    }
}
