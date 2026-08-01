<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class PreliminaryRowPolicyTest extends TestCase
{
    public function test_locked_mark_and_status_matrix(): void
    {
        $decide = static function (?string $mark, ?string $status): array {
            $hasMark = trim((string) $mark) !== '';
            $hasStatus = trim((string) $status) !== '';

            return match (true) {
                $hasMark && $hasStatus => ['active', true, true],
                $hasMark => ['active', true, false],
                $hasStatus => ['cancelled', false, false],
                default => ['cancelled', false, true],
            };
        };

        self::assertSame(['active', true, false], $decide('72.50', null));
        self::assertSame(['active', true, true], $decide('72.50', 'Source note'));
        self::assertSame(['cancelled', false, false], $decide(null, 'Cancelled by authority'));
        self::assertSame(['cancelled', false, true], $decide(null, null));
    }
}
