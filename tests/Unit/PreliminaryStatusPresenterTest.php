<?php

namespace Tests\Unit;

use App\Enums\PreliminaryProcessingStatus;
use App\Support\PreliminaryStatusPresenter;
use PHPUnit\Framework\TestCase;

final class PreliminaryStatusPresenterTest extends TestCase
{
    public function test_it_turns_processing_states_into_human_readable_labels(): void
    {
        self::assertSame('Result finalized', PreliminaryStatusPresenter::label(PreliminaryProcessingStatus::ResultFinalized));
        self::assertSame('Needs reprocessing', PreliminaryStatusPresenter::label(PreliminaryProcessingStatus::Reopened));
        self::assertSame('Waiting for validation', PreliminaryStatusPresenter::label('validation_queued'));
    }

    public function test_it_uses_status_aware_badges(): void
    {
        self::assertStringContainsString('green', PreliminaryStatusPresenter::badgeClass('completed'));
        self::assertStringContainsString('yellow', PreliminaryStatusPresenter::badgeClass('reopened'));
        self::assertStringContainsString('red', PreliminaryStatusPresenter::badgeClass('failed'));
    }
}
