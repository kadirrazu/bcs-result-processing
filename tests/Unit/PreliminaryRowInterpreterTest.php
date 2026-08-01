<?php

namespace Tests\Unit;

use App\Services\Preliminary\PreliminaryRowInterpreter;
use PHPUnit\Framework\TestCase;

final class PreliminaryRowInterpreterTest extends TestCase
{
    public function test_mark_without_status_is_active_without_warning(): void
    {
        $result = (new PreliminaryRowInterpreter())->interpret('72.50', null);

        self::assertSame('72.50', $result['mark']);
        self::assertSame('active', $result['candidate_status']);
        self::assertSame([], $result['warnings']);
        self::assertSame([], $result['errors']);
    }

    public function test_mark_with_status_accepts_mark_and_preserves_warning_semantics(): void
    {
        $result = (new PreliminaryRowInterpreter())->interpret('72.50', 'Document verification pending');

        self::assertSame('72.50', $result['mark']);
        self::assertSame('active', $result['candidate_status']);
        self::assertNotEmpty($result['warnings']);
        self::assertSame([], $result['errors']);
    }

    public function test_blank_mark_with_status_is_cancelled_without_warning(): void
    {
        $result = (new PreliminaryRowInterpreter())->interpret(null, 'Cancelled by authority');

        self::assertNull($result['mark']);
        self::assertSame('cancelled', $result['candidate_status']);
        self::assertSame([], $result['warnings']);
        self::assertSame([], $result['errors']);
    }

    public function test_blank_mark_and_blank_status_is_cancelled_with_warning(): void
    {
        $result = (new PreliminaryRowInterpreter())->interpret(null, null);

        self::assertNull($result['mark']);
        self::assertSame('cancelled', $result['candidate_status']);
        self::assertNotEmpty($result['warnings']);
        self::assertSame([], $result['errors']);
    }

    public function test_non_numeric_mark_is_invalid_not_silently_accepted(): void
    {
        $result = (new PreliminaryRowInterpreter())->interpret('ABC', null);

        self::assertNull($result['mark']);
        self::assertSame('cancelled', $result['candidate_status']);
        self::assertNotEmpty($result['errors']);
    }
}
