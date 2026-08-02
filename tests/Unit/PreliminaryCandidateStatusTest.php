<?php

namespace Tests\Unit;

use App\Enums\PreliminaryCandidateStatus;
use PHPUnit\Framework\TestCase;

final class PreliminaryCandidateStatusTest extends TestCase
{
    public function test_preliminary_processing_status_options_are_locked(): void
    {
        self::assertSame(
            ['active', 'cancelled', 'withheld', 'expelled'],
            array_map(static fn (PreliminaryCandidateStatus $status): string => $status->value, PreliminaryCandidateStatus::cases()),
        );
    }
}
