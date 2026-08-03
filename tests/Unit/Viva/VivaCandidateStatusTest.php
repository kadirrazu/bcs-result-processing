<?php

namespace Tests\Unit\Viva;

use App\Enums\VivaCandidateStatus;
use PHPUnit\Framework\TestCase;

final class VivaCandidateStatusTest extends TestCase
{
    public function test_operational_status_options_are_locked(): void
    {
        self::assertSame(
            ['active', 'cancelled', 'withheld', 'expelled'],
            array_map(static fn (VivaCandidateStatus $status): string => $status->value, VivaCandidateStatus::cases()),
        );
    }
}
