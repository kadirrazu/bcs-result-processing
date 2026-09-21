<?php

namespace Tests\Feature\NonCadre;

use PHPUnit\Framework\TestCase;

final class NonCadreAllocationHistoricalExclusionContractTest extends TestCase
{
    public function test_nc4_historical_exclusion_contract_is_present(): void
    {
        $root=dirname(__DIR__,3);
        $service=file_get_contents($root.'/app/Services/NonCadre/Allocation/NonCadreAllocationService.php');
        $report=file_get_contents($root.'/app/Services/NonCadre/Reporting/NonCadreReportingService.php');
        $this->assertStringContainsString("where('historical_excluded',false)",$service);
        $this->assertStringContainsString('NO_PRIOR_CADRE_RECOMMENDATION_OR_DISQUALIFYING_EMPLOYMENT',$service);
        $this->assertStringContainsString('historical_exclusion_reason',$report);
        $this->assertStringContainsString('Previous BCS Positive',file_get_contents($root.'/resources/views/non-cadre/allocation/index.blade.php'));
        $this->assertStringContainsString('historical_exclusion_reason',file_get_contents($root.'/resources/views/non-cadre/reporting/common.blade.php'));
    }
}
