<?php

namespace Tests\Feature\Allocation;

use Tests\TestCase;

final class ManualAllocationReadyChoiceAdjustmentContractTest extends TestCase
{
    public function test_optional_manual_adjustment_preserves_legacy_choice_when_unused_and_only_excludes_existing_choices(): void
    {
        $final = file_get_contents(app_path('Services/ChoiceOptimization/FinalAllocationReadyChoiceService.php'));
        $adjust = file_get_contents(app_path('Services/Allocation/ManualAllocationChoiceAdjustmentService.php'));
        $freeze = file_get_contents(app_path('Services/Allocation/AllocationInputFreezeService.php'));

        self::assertStringContainsString('if ($this->activeExclusions()->isEmpty()) return $baseChoiceOptimizationHash;', $final);
        self::assertStringContainsString('return $this->finalChoices->effectiveMap();', $freeze);
        self::assertStringContainsString('Only an existing Allocation Ready Choice may be adjusted', $adjust);
        self::assertStringContainsString("record(\$registrationId,\$choiceCode,'EXCLUDE'", str_replace(' ', '', $adjust));
        self::assertStringNotContainsString('seatBreakup->', $adjust);
        self::assertStringContainsString('Seat Breakup remains current', $adjust);
    }

    public function test_manual_adjustment_stales_allocation_lineage_but_not_choice_optimization_or_seat_breakup(): void
    {
        $adjust = file_get_contents(app_path('Services/Allocation/ManualAllocationChoiceAdjustmentService.php'));

        self::assertStringContainsString("'status'=>'stale'", str_replace(' ', '', $adjust));
        self::assertStringContainsString("where('status','frozen')->update(['status'=>'stale'", str_replace(' ', '', $adjust));
        self::assertStringContainsString('staleA3AndA4(', $adjust);
        self::assertStringNotContainsString('ChoiceOptimizationProcessingState', $adjust);
        self::assertStringNotContainsString('AllocationSeatBreakupVersion', $adjust);
    }

    public function test_manual_adjustment_ui_final_view_and_reporting_explanation_contract(): void
    {
        $routes = file_get_contents(base_path('routes/choice-optimization.php'));
        $landing = file_get_contents(resource_path('views/choice-optimization/index.blade.php'));
        $manualIndex = file_get_contents(resource_path('views/choice-optimization/manual-adjustment/index.blade.php'));
        $manualShow = file_get_contents(resource_path('views/choice-optimization/manual-adjustment/show.blade.php'));
        $finalIndex = file_get_contents(resource_path('views/choice-optimization/final-allocation-ready-choice/index.blade.php'));
        $reportService = file_get_contents(app_path('Services/Reporting/AllocationVerificationReportService.php'));
        $reportTable = file_get_contents(resource_path('views/reporting/allocation-verification/_verification-table.blade.php'));

        self::assertStringContainsString('final-allocation-ready-choice', $routes);
        self::assertTrue(
            strpos($landing, 'Manual Adjustment of Allocation Ready Choice')
            < strpos($landing, 'Final Allocation Ready Choice')
        );
        self::assertStringContainsString('choice-code-lane', $manualIndex);
        self::assertStringContainsString('<th>Post Abbreviation</th>', $manualShow);
        self::assertStringContainsString('Manual Adjustment', $finalIndex);
        self::assertStringContainsString('Final Allocation Ready Choice', $finalIndex);
        self::assertStringContainsString('Manual Choice Removal:', $reportService);
        self::assertStringContainsString('manual_adjustment_notes', $reportTable);
    }

    public function test_manual_adjustment_status_filter_and_collection_safe_final_choice_contract(): void
    {
        $indexView = file_get_contents(resource_path('views/choice-optimization/manual-adjustment/index.blade.php'));
        $controller = file_get_contents(app_path('Http/Controllers/ManualAllocationChoiceAdjustmentController.php'));
        $service = file_get_contents(app_path('Services/ChoiceOptimization/FinalAllocationReadyChoiceService.php'));

        self::assertStringContainsString('name="status"', $indexView);
        self::assertStringContainsString('Has Adjustment History', $indexView);
        self::assertStringContainsString('Unchanged', $indexView);
        self::assertStringContainsString("['all', 'adjusted', 'unchanged']", $controller);
        self::assertStringContainsString("whereIn('choice_optimization_historical_choices.registration_id'", $controller);
        self::assertStringNotContainsString("array_map('strval', \$codes)", $service);
        self::assertStringContainsString(
            '$codes->map(static fn ($code): string => (string) $code)->all()',
            $service
        );
    }
}
