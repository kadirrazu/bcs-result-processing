<?php

namespace Tests\Feature\ChoiceOptimization;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ChoiceOptimizationCo1FoundationContractTest extends TestCase
{
    #[Test]
    public function co1_foundation_contract_is_present(): void
    {
        $root = base_path();

        $this->assertFileExists($root.'/config/choice-optimization.php');
        $this->assertFileExists($root.'/routes/choice-optimization.php');
        $this->assertFileExists($root.'/app/Services/ChoiceOptimization/ChoiceOptimizationSettingsService.php');
        $this->assertFileExists($root.'/resources/views/choice-optimization/index.blade.php');

        $migration = file_get_contents($root.'/database/examination-migrations/2026_08_22_100000_create_choice_optimization_foundation.php');
        $this->assertStringContainsString('choice_optimization_settings', $migration);
        $this->assertStringContainsString('choice_optimization_processing_states', $migration);
        $this->assertStringContainsString('choice_optimization_processing_audits', $migration);

        $config = require $root.'/config/choice-optimization.php';
        $this->assertFalse($config['default_enabled']);
        $this->assertSame([
            'bcs_number', 'reg', 'name', 'fname', 'mname', 'b_date', 'district_name',
            'ssc_roll', 'ssc_year', 'hsc_roll', 'hsc_year', 'nid', 'cadre',
        ], $config['previous_bcs_columns']);
    }
}
