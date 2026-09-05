<?php

namespace Tests\Feature\Allocation;

use Tests\TestCase;

final class AllocationA6FastPublishingGateContractTest extends TestCase
{
    public function test_a6_does_not_repeat_full_upstream_integrity_verification(): void
    {
        $source = file_get_contents(app_path('Services/Allocation/AllocationA6ReadinessService.php'));

        $this->assertStringNotContainsString('inspectStrict(', $source);
        $this->assertStringNotContainsString('inspectDashboard(', $source);
        $this->assertStringContainsString("latest('version')", $source);
        $this->assertStringContainsString('allocation_a4_run_id', $source);
        $this->assertStringContainsString('a4_output_hash', $source);
        $this->assertStringContainsString('candidate_result_hash', $source);
        $this->assertStringContainsString('capacity_result_hash', $source);
    }

    public function test_export_worker_labels_the_fast_gate_as_source_verification(): void
    {
        $source = file_get_contents(app_path('Jobs/ProcessAllocationA6Export.php'));

        $this->assertStringContainsString('VERIFYING_SOURCE', $source);
        $this->assertStringNotContainsString('VERIFYING_INTEGRITY', $source);
    }
}
