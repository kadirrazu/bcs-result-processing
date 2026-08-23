<?php

namespace Tests\Feature\ChoiceOptimization;

use Tests\TestCase;

class ChoiceOptimizationCo4b1CadreWarningSearchFilterContractTest extends TestCase
{
    public function test_cadre_master_mismatch_is_warning_only_and_does_not_block_effective_approval(): void
    {
        $service = file_get_contents(
            app_path('Services/PreviousBcsRepository/PreviousBcsRepositoryValidationService.php')
        );
        $authority = file_get_contents(
            app_path('Services/PreviousBcsRepository/PreviousBcsRepositoryAuthorityService.php')
        );

        $this->assertStringContainsString('CADRE_MASTER_MISMATCH', $service);
        $this->assertStringContainsString('$warnings[]', $service);
        $this->assertStringNotContainsString('UNKNOWN_CADRE_ABBR', $service);
        $this->assertStringContainsString('\'cadre\' => $cadre !== \'\' ? $cadre : null', $service);

        // Effective approval checks blocking invalid rows and dataset integrity, not warnings.
        $this->assertStringContainsString('invalid_rows', $authority);
        $this->assertStringNotContainsString('validation_warnings', $authority);
    }

    public function test_previous_bcs_dataset_rows_support_multifield_search_and_filters(): void
    {
        $controller = file_get_contents(
            app_path('Http/Controllers/PreviousBcsRepositoryController.php')
        );
        $view = file_get_contents(
            resource_path('views/previous-bcs-repository/show.blade.php')
        );

        foreach ([
            "'reg', 'like'",
            "'name', 'like'",
            "'fname', 'like'",
            "'mname', 'like'",
            "'dist_name', 'like'",
            "'ssc_roll', 'like'",
            "'hsc_roll', 'like'",
            "'nid_no', 'like'",
            "'cadre', 'like'",
        ] as $needle) {
            $this->assertStringContainsString($needle, $controller);
        }

        $this->assertStringContainsString('if ($status === \'warning\')', $controller);
        $this->assertStringContainsString("whereNotNull('validation_warnings')", $controller);
        $this->assertStringContainsString("'ssc_year'", $controller);
        $this->assertStringContainsString("'hsc_year'", $controller);

        $this->assertStringContainsString('Apply Filters', $view);
        $this->assertStringContainsString('Warning', $view);
        $this->assertStringContainsString('SSC Year', $view);
        $this->assertStringContainsString('HSC Year', $view);
    }
}
