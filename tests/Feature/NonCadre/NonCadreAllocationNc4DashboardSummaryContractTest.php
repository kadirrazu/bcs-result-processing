<?php

namespace Tests\Feature\NonCadre;

use Tests\TestCase;

final class NonCadreAllocationNc4DashboardSummaryContractTest extends TestCase
{
    public function test_nc4_dashboard_and_run_views_keep_operational_summary_contract(): void
    {
        $index=file_get_contents(resource_path('views/non-cadre/allocation/index.blade.php'));
        $run=file_get_contents(resource_path('views/non-cadre/allocation/run.blade.php'));
        $service=file_get_contents(app_path('Services/NonCadre/Allocation/NonCadreAllocationService.php'));

        $this->assertStringContainsString('Processing Status Board',$index);
        $this->assertStringContainsString('Quota Allocated',$index);
        $this->assertStringContainsString('Post-wise Allocation Summary',$run);
        $this->assertStringContainsString('Candidate Search',$run);
        $this->assertStringContainsString("paginate(20)",$service);
        $this->assertStringContainsString('postWiseSummary',$service);
        $this->assertStringContainsString('quota_allocated_count',$service);
    }
}
