<?php

namespace Tests\Unit\Written;

use App\Services\Written\WrittenSubjectConfig;
use Tests\TestCase;

final class WrittenSubjectConfigTest extends TestCase
{
    public function test_track_composition_and_totals_match_locked_baseline(): void
    {
        $config = app(WrittenSubjectConfig::class);

        $this->assertSame(['002', '003', '005', '007', '008', '009', '010'], $config->trackSubjects('general'));
        $this->assertSame(['001', '003', '005', '007', '008', '009', 'PRS'], $config->trackSubjects('technical'));
        $this->assertSame(900.0, $config->trackFullMark('general'));
        $this->assertSame(900.0, $config->trackFullMark('technical'));
    }

    public function test_percentage_thresholds_are_derived_not_fixed_literals(): void
    {
        $config = app(WrittenSubjectConfig::class);

        $this->assertSame(450.0, $config->trackPassThreshold('general'));
        $this->assertSame(60.0, $config->paperCrashThreshold('003'));
        $this->assertSame(150.0, $config->highMarkThreshold('003'));
        $this->assertSame(['008', '009'], $config->combined008009());
    }
}
