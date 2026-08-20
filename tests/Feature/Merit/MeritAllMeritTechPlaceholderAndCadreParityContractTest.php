<?php

namespace Tests\Feature\Merit;

use Tests\TestCase;

final class MeritAllMeritTechPlaceholderAndCadreParityContractTest extends TestCase
{
    public function test_all_merit_tech_placeholder_and_cadre_display_export_parity(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/MeritController.php'));
        $results = file_get_contents(resource_path('views/merit/results.blade.php'));
        $cadre = file_get_contents(resource_path('views/merit/cadre.blade.php'));

        $this->assertStringContainsString(
            '$allMeritTechDisplay === \'[]\' ? \'-\' : $allMeritTechDisplay',
            $results
        );

        $this->assertStringContainsString(
            '$allMeritTech === \'[]\' ? \'-\' : $allMeritTech',
            $controller
        );

        $this->assertStringContainsString(
            "leftJoin('tabulation_results as t', 't.id', '=', 'm.tabulation_result_id')",
            $controller
        );
        $this->assertStringContainsString(
            "'t.general_grand_total'",
            $controller
        );
        $this->assertStringContainsString(
            "'t.technical_grand_total'",
            $controller
        );

        foreach ([
            'Cadre Merit',
            'Grand Total (G/T)',
            'Source Merit',
            'Choice Position',
            'Common',
            'General',
            'Technical',
        ] as $heading) {
            $this->assertStringContainsString($heading, $cadre);
        }

        foreach ([
            'cadre_merit_position',
            'source_merit_position',
            'choice_position',
            'common_merit_position',
            'general_merit_position',
            'technical_merit_position',
        ] as $field) {
            $this->assertStringContainsString($field, $cadre);
        }

        $grandTotal = strpos($cadre, 'Grand Total (G/T)');
        $sourceMerit = strpos($cadre, 'Source Merit');

        $this->assertNotFalse($grandTotal);
        $this->assertNotFalse($sourceMerit);
        $this->assertLessThan($sourceMerit, $grandTotal);

        $this->assertStringContainsString(
            'General Grand Total',
            $controller
        );
        $this->assertStringContainsString(
            'Technical Grand Total',
            $controller
        );
    }
}
