<?php

namespace Tests\Unit\Written;

use App\Services\Written\WrittenSubjectConfig;
use Tests\TestCase;

final class WrittenRuleContractTest extends TestCase
{
    public function test_written_track_totals_and_thresholds_are_config_driven(): void
    {
        $rules = app(WrittenSubjectConfig::class);
        $this->assertSame(900.0, $rules->trackFullMark('general'));
        $this->assertSame(900.0, $rules->trackFullMark('technical'));
        $this->assertSame(450.0, $rules->trackPassThreshold('general'));
        $this->assertSame(450.0, $rules->trackPassThreshold('technical'));
        $this->assertSame(['008', '009'], $rules->combined008009());
    }
}
