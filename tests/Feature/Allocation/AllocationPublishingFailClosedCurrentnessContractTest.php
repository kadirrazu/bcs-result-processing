<?php

namespace Tests\Feature\Allocation;

use Tests\TestCase;

final class AllocationPublishingFailClosedCurrentnessContractTest extends TestCase
{
    public function test_a6_fails_closed_on_allocation_dashboard_currentness_metadata(): void
    {
        $service = file_get_contents(app_path('Services/Allocation/AllocationA6ReadinessService.php'));
        $this->assertStringContainsString('AllocationReadinessService $allocationReadiness', $service);
        $this->assertStringContainsString('$this->allocationReadiness->inspectDashboard()', $service);
        $this->assertStringContainsString('Allocation publishing authority is not current:', $service);
    }

    public function test_lazy_merit_stale_detection_propagates_to_allocation(): void
    {
        $service = file_get_contents(app_path('Services/Merit/MeritStaleService.php'));
        $this->assertStringContainsString('DownstreamStalePropagationService $downstream', $service);
        $this->assertStringContainsString("\$this->downstream->propagate('merit', \$reason)", $service);
    }

    public function test_allocation_landing_repairs_historical_downstream_currentness_and_summary_fails_closed(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/AllocationController.php'));
        $view = file_get_contents(resource_path('views/allocation/index.blade.php'));

        $this->assertStringContainsString("\$dashboardReadiness = \$readiness->inspectDashboard()", $controller);
        $this->assertStringContainsString("\$runStale->staleFromDirectInputChange(", $controller);
        $this->assertStringContainsString('$effectiveAllocationBlocked', $view);
        $this->assertStringContainsString('Direct Allocation prerequisites are NOT READY', $view);
    }

    public function test_dynamic_query_allocation_source_uses_same_publishing_gate_as_a6(): void
    {
        $authority = file_get_contents(app_path('Services/Reporting/DynamicQuery/DynamicReportAuthority.php'));
        $this->assertStringContainsString('AllocationA6ReadinessService $allocationReadiness', $authority);
        $this->assertStringContainsString('$allocationGate = $this->allocationReadiness->inspect()', $authority);
        $this->assertStringContainsString("(\$allocationGate['ready'] ?? false)", $authority);
    }
}
