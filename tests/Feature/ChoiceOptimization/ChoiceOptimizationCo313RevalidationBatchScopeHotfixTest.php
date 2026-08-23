<?php

namespace Tests\Feature\ChoiceOptimization;

use Tests\TestCase;

class ChoiceOptimizationCo313RevalidationBatchScopeHotfixTest extends TestCase
{
    public function test_omr_validation_transaction_captures_batch_for_configured_choice_limit(): void
    {
        $service = file_get_contents(
            app_path('Services/ChoiceOptimization/ChoiceOptimizationOmrValidationService.php')
        );

        $this->assertStringContainsString(
            'function () use ($batch, $chunk, $written, $registrations, $duplicateCounts, $entries',
            $service
        );

        $this->assertStringContainsString(
            '(int) $batch->configured_maximum_choices',
            $service
        );
    }
}
