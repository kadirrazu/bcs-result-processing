<?php

namespace Tests\Feature\HistoricalPunishment;

use PHPUnit\Framework\TestCase;

final class HistoricalPunishmentQueueOptimizationContractTest extends TestCase
{
    public function test_screening_is_queued_chunked_and_does_not_materialise_population_ids(): void
    {
        $service = file_get_contents(base_path('app/Services/HistoricalPunishment/HistoricalPunishmentScreeningService.php'));
        $job = file_get_contents(base_path('app/Jobs/ProcessHistoricalPunishmentScreening.php'));
        $controller = file_get_contents(base_path('app/Http/Controllers/HistoricalPunishmentController.php'));

        $this->assertStringContainsString('chunkById(self::CHUNK_SIZE', $service);
        $this->assertStringContainsString('whereExists', $service);
        $this->assertStringNotContainsString("whereIn('id',\$ids)", str_replace(' ', '', $service));
        $this->assertStringContainsString('implements ShouldQueue', $job);
        $this->assertStringContainsString('ProcessHistoricalPunishmentScreening::dispatch', $controller);
        $this->assertStringContainsString("whereIn('status', ['queued', 'running'])", $controller);
    }
}
