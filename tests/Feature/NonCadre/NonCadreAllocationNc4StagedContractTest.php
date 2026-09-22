<?php
namespace Tests\Feature\NonCadre;
use PHPUnit\Framework\Attributes\Test; use Tests\TestCase;
final class NonCadreAllocationNc4StagedContractTest extends TestCase
{
 #[Test] public function nc4_is_staged_and_phase2_contains_nm_shifting_contract():void{
  $s=file_get_contents(app_path('Services/NonCadre/Allocation/NonCadreAllocationService.php'));
  foreach(['function processFreeze(','function phase1(','function phase2(','function validate(','convertedQuota','QUOTA_TO_MERIT','SHIFTED','NM','NO_CADRE_ALLOCATED_CANDIDATE','ALLOCATION_READY_CHOICE','SEAT_CONSERVATION'] as $x)$this->assertStringContainsString($x,$s);
 }
 #[Test] public function nc4_routes_follow_stage_order():void{$r=file_get_contents(base_path('routes/non-cadre.php'));foreach(["/freeze","/phase-1","/phase-2","/validate","/finalize"] as $x)$this->assertStringContainsString($x,$r);}
}
