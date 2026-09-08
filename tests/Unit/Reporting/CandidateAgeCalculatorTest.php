<?php

namespace Tests\Unit\Reporting;

use App\Services\Reporting\CandidateAgeCalculator;
use PHPUnit\Framework\TestCase;

final class CandidateAgeCalculatorTest extends TestCase
{
    public function test_it_calculates_calendar_age_from_reference_date(): void
    {
        $age = (new CandidateAgeCalculator())->calculate('1998-12-20', '2026-09-08');

        self::assertSame(27, $age['years']);
        self::assertSame(8, $age['months']);
        self::assertSame(19, $age['days']);
        self::assertSame('27 Years 8 Months 19 Days', $age['label']);
    }
}
