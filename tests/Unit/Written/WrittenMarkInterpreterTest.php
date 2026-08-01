<?php

namespace Tests\Unit\Written;

use App\Services\Written\WrittenMarkInterpreter;
use PHPUnit\Framework\TestCase;

final class WrittenMarkInterpreterTest extends TestCase
{
    public function test_aaa_is_normalized_to_abs(): void
    {
        $result = (new WrittenMarkInterpreter())->interpret('AAA', 100);
        self::assertSame('absent', $result['kind']);
        self::assertSame('ABS', $result['normalized']);
        self::assertNull($result['actual_mark']);
    }

    public function test_blank_is_preserved_for_validation_to_decide_applicability(): void
    {
        $result = (new WrittenMarkInterpreter())->interpret('', 100);
        self::assertSame('blank', $result['kind']);
        self::assertNull($result['error']);
    }

    public function test_numeric_mark_is_preserved_as_actual_mark(): void
    {
        $result = (new WrittenMarkInterpreter())->interpret('75.5', 100);
        self::assertSame('numeric', $result['kind']);
        self::assertSame(75.5, $result['actual_mark']);
    }

    public function test_mark_above_full_mark_is_invalid(): void
    {
        $result = (new WrittenMarkInterpreter())->interpret('101', 100);
        self::assertSame('invalid', $result['kind']);
        self::assertNotNull($result['error']);
    }
}
