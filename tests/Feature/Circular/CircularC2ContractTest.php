<?php

namespace Tests\Feature\Circular;

use Tests\TestCase;

final class CircularC2ContractTest extends TestCase
{
    public function test_c2_excel_ui_and_real_circular_view_contract_is_present(): void
    {
        $this->assertSame('|', config('circular.code_delimiter'));
        $this->assertSame([
            'cadre_serial','sub_serial','cadre_code','sub_cadre_code','cadre_type','post_count',
            'bachelor_subject_codes','prs_codes','status','note',
        ], config('circular.headers'));

        $routes = file_get_contents(base_path('routes/circular.php'));
        $this->assertStringContainsString("name('import.upload')", $routes);
        $this->assertStringContainsString("name('import.approve')", $routes);
        $this->assertStringContainsString("name('entries.create')", $routes);
        $this->assertStringContainsString("name('view')", $routes);

        $migration = file_get_contents(database_path('examination-migrations/2026_08_10_010000_upgrade_circular_module_to_c2.php'));
        $this->assertStringContainsString('circular_import_staging', $migration);
        $this->assertStringContainsString("dropUnique('circular_entries_effective_code_unique')", $migration);
        $this->assertStringContainsString('cadre_name_snapshot', $migration);
    }
}
