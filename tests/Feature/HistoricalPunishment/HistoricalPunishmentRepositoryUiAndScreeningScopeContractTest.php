<?php

namespace Tests\Feature\HistoricalPunishment;

use Tests\TestCase;

final class HistoricalPunishmentRepositoryUiAndScreeningScopeContractTest extends TestCase
{
    public function test_repository_management_and_module_screening_contract(): void
    {
        $routes = file_get_contents(base_path('routes/historical-punishments.php'));
        $index = file_get_contents(resource_path('views/historical-punishments/index.blade.php'));
        $screen = file_get_contents(app_path('Services/HistoricalPunishment/HistoricalPunishmentScreeningService.php'));

        $this->assertStringContainsString("'/create'", $routes);
        $this->assertStringContainsString("'/batch-import'", $routes);
        $this->assertStringContainsString('/{historicalPunishment}/edit', $routes);
        $this->assertStringContainsString('Punishment Active', $index);
        $this->assertStringContainsString('Punishment Expired', $index);
        $this->assertStringContainsString('Delete', $index);

        foreach (['preliminary_initial','preliminary_final','written_initial','written_final','merit_initial','merit_final','allocation_final','noncadre_final'] as $phase) {
            $this->assertStringContainsString("phase === '{$phase}'", $screen);
        }
    }

    public function test_repository_landing_has_summary_bcs_search_serial_and_25_row_pagination_contract(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/HistoricalPunishmentController.php'));
        $view = file_get_contents(resource_path('views/historical-punishments/index.blade.php'));
        $compact = preg_replace('/\\s+/', '', $controller);

        $this->assertStringContainsString("->where('bcs',\$q)", $compact);
        $this->assertStringContainsString('paginate(25)', $compact);
        $this->assertStringContainsString("'summary'", $controller);
        $this->assertStringContainsString('Total Records', $view);
        $this->assertStringContainsString('Punishment Active', $view);
        $this->assertStringContainsString('Punishment Expired', $view);
        $this->assertStringContainsString('BCS / REG / Name / NID', $view);
        $this->assertStringContainsString('$rows->firstItem() + $loop->index', $view);
    }

    public function test_sample_import_contains_fixed_term_date_format_examples(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/HistoricalPunishmentController.php'));
        $this->assertStringContainsString("'2026-01-15','2028-12-31'", str_replace(' ', '', $controller));
        $this->assertStringContainsString("'TRUE','',''", str_replace(' ', '', $controller));
    }
}
