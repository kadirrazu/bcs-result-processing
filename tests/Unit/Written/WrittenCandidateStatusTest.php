<?php

namespace Tests\Unit\Written;

use App\Enums\WrittenCandidateStatus;
use PHPUnit\Framework\TestCase;

final class WrittenCandidateStatusTest extends TestCase
{
    public function test_written_processing_status_options_are_locked(): void
    {
        self::assertSame(
            ['active', 'cancelled', 'withheld', 'expelled'],
            array_map(static fn (WrittenCandidateStatus $status): string => $status->value, WrittenCandidateStatus::cases()),
        );
    }
}
