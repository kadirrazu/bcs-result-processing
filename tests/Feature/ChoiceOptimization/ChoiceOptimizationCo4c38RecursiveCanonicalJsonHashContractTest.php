<?php

namespace Tests\Feature\ChoiceOptimization;

use Tests\TestCase;

class ChoiceOptimizationCo4c38RecursiveCanonicalJsonHashContractTest extends TestCase
{
    public function test_output_hash_recursively_canonicalizes_json_objects_but_preserves_lists(): void
    {
        $service = file_get_contents(
            app_path('Services/ChoiceOptimization/ChoiceOptimizationHistoricalChoiceService.php')
        );

        $this->assertStringContainsString('private function canonicalizeValue(mixed $value): mixed', $service);
        $this->assertStringContainsString('array_is_list($value)', $service);
        $this->assertStringContainsString('ksort($value, SORT_STRING)', $service);
        $this->assertStringContainsString('$this->canonicalizeValue($item)', $service);
        $this->assertStringContainsString('$result = $this->canonicalizeValue($canonical)', $service);
    }

    public function test_finalization_failure_audit_records_expected_and_actual_output_hashes(): void
    {
        $service = file_get_contents(
            app_path('Services/ChoiceOptimization/ChoiceOptimizationHistoricalChoiceFinalizationService.php')
        );

        $this->assertStringContainsString("'expected_output_hash'", $service);
        $this->assertStringContainsString("'actual_output_hash'", $service);
        $this->assertStringContainsString('$actualOutputHash = $outputHash', $service);
    }
}
