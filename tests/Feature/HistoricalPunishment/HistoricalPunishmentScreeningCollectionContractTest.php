<?php

namespace Tests\Feature\HistoricalPunishment;

use Tests\TestCase;

final class HistoricalPunishmentScreeningCollectionContractTest extends TestCase
{
    public function test_historical_rows_are_converted_to_base_collection_before_grouping_and_except(): void
    {
        $service = file_get_contents(app_path('Services/HistoricalPunishment/HistoricalPunishmentScreeningService.php'));

        self::assertMatchesRegularExpression('/->get\\(\\)\\s*->toBase\\(\\)\\s*->groupBy/s', $service);
        self::assertStringContainsString("->except('__NONE__')", $service);
    }
}
