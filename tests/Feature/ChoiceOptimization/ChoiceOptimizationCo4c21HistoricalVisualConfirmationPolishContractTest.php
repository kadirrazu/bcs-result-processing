<?php

namespace Tests\Feature\ChoiceOptimization;

use Tests\TestCase;

class ChoiceOptimizationCo4c21HistoricalVisualConfirmationPolishContractTest extends TestCase
{
    public function test_historical_list_uses_bcs_context_symmetric_candidate_order_and_serial(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/ChoiceOptimizationController.php'));
        $view = file_get_contents(resource_path('views/choice-optimization/historical-show.blade.php'));

        $this->assertStringContainsString('$currentBcsNumber = $context->current()?->bcs_number', $controller);

        $this->assertStringContainsString('Current Candidate <strong>(BCS {{ $currentBcsNumber ?: \'—\' }})</strong>', $view);
        $this->assertStringContainsString('Previous BCS Record <strong>(BCS {{ $source->previous_bcs_number }})</strong>', $view);
        $this->assertStringContainsString('class="text-center align-middle"', $view);
        $this->assertStringContainsString('($matches->firstItem() ?? 1) + $loop->index', $view);

        $currentReg = strpos($view, '<strong>Reg:</strong> <code>{{ $match->current_reg }}');
        $currentName = strpos($view, '{{ $match->registration?->name ?: \'—\' }}');
        $previousReg = strpos($view, '<strong>Reg:</strong> <code>{{ $match->previous_reg ?: \'—\' }}');
        $previousName = strpos($view, '{{ $match->previous_name ?: \'—\' }}');

        $this->assertNotFalse($currentReg);
        $this->assertNotFalse($currentName);
        $this->assertNotFalse($previousReg);
        $this->assertNotFalse($previousName);
        $this->assertLessThan($currentName, $currentReg);
        $this->assertLessThan($previousName, $previousReg);
    }

    public function test_individual_match_resolves_district_title_and_operator_user_name_at_runtime(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/ChoiceOptimizationController.php'));
        $view = file_get_contents(resource_path('views/choice-optimization/historical-match-show.blade.php'));

        $this->assertStringContainsString('District::query()', $controller);
        $this->assertStringContainsString('->where(\'code\', (string) $match->registration->district_code)', $controller);
        $this->assertStringContainsString('User::query()->find((int) $match->resolved_by', $controller);

        $this->assertStringContainsString('.\' - \'.($currentDistrict?->name ?: \'Unresolved\')', $view);
        $this->assertStringContainsString('Confirmed by:', $view);
        $this->assertStringContainsString('User #{{ $match->resolved_by', $view);
        $this->assertStringContainsString('({{ $resolvedByUser->name }})', $view);
        $this->assertStringContainsString('Current Candidate <strong>(BCS {{ $currentBcsNumber ?: \'—\' }})</strong>', $view);
        $this->assertStringContainsString('Previous BCS Record <strong>(BCS {{ $source->previous_bcs_number }})</strong>', $view);
    }
}
