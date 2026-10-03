<?php

namespace Tests\Feature\HistoricalPunishment;

use Tests\TestCase;

final class HistoricalPunishmentRepositoryContractTest extends TestCase
{
    public function test_repository_and_optional_screening_contract_is_wired(): void
    {
        $web = file_get_contents(base_path('routes/web.php'));
        $repo = file_get_contents(base_path('routes/historical-punishments.php'));
        $service = file_get_contents(app_path('Services/HistoricalPunishment/HistoricalPunishmentScreeningService.php'));
        $view = file_get_contents(resource_path('views/historical-punishments/screening.blade.php'));

        self::assertStringContainsString('historical-punishments.php', $web);
        self::assertStringContainsString('historical-punishment-screening.php', $web);
        self::assertStringContainsString("->name('historical-punishments.')", $repo);
        self::assertStringContainsString('screening.run', $view);
        self::assertStringContainsString('co4c1-core-v1', $service);
        self::assertMatchesRegularExpression("/where\\(\\s*'status'\\s*,\\s*'active'\\s*\\)/", $service);
    }

    public function test_lifetime_contract_clears_dates(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/HistoricalPunishmentController.php'));
        // Keep this contract semantic rather than coupling it to one array/ternary layout.
        self::assertStringContainsString('punishment_start', $controller);
        self::assertStringContainsString('punishment_end', $controller);
        self::assertMatchesRegularExpression('/\$life.*?punishment_start.*?null.*?punishment_end.*?null/s', $controller);
    }
}
