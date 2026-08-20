<?php

namespace Tests\Feature\Merit;

use Tests\TestCase;

final class MeritResultsAlignmentAndExcelExportContractTest extends TestCase
{
    public function test_merit_listing_alignment_and_excel_export_match_operator_columns(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/MeritController.php'));
        $view = file_get_contents(resource_path('views/merit/results.blade.php'));

        foreach (['Category', 'Track', 'Common', 'General', 'Technical'] as $heading) {
            $this->assertStringContainsString(
                '<th class="text-center">'.$heading.'</th>',
                $view
            );
        }

        $this->assertStringContainsString(
            'class="text-center">{{ $r->common_merit_position',
            $view
        );
        $this->assertStringContainsString(
            'class="text-center">{{ $r->general_merit_position',
            $view
        );
        $this->assertStringContainsString(
            'class="text-center">{{ $r->technical_merit_position',
            $view
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

        $this->assertStringContainsString(
            "1 => 'GG'",
            $controller
        );
        $this->assertStringContainsString(
            "2 => 'TT'",
            $controller
        );
        $this->assertStringContainsString(
            "3 => 'GT'",
            $controller
        );

        foreach ([
            "'Registration Category'",
            "'Written Qualified Track'",
            "'General Grand Total'",
            "'Technical Grand Total'",
            "'Common Merit'",
            "'General Merit'",
            "'Technical Merit'",
            "'all_merit_tech'",
            "'Status'",
        ] as $header) {
            $this->assertStringContainsString($header, $controller);
        }

        $this->assertStringContainsString(
            'MeritResult::allMeritTechJson($row->all_merit_tech)',
            $controller
        );
    }
}
