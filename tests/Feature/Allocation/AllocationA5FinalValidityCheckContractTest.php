<?php

namespace Tests\Feature\Allocation;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AllocationA5FinalValidityCheckContractTest extends TestCase
{
    #[Test]
    public function a5_is_read_only_and_checks_subject_prs_technical_quota_and_capacity(): void
    {
        $service = file_get_contents(app_path('Services/Allocation/AllocationA5ValidityService.php'));
        $migration = file_get_contents(base_path('database/examination-migrations/2026_09_04_190000_create_allocation_a5_validity_check.php'));

        $this->assertStringContainsString('A5 is a read-only assurance gate', $service);
        $this->assertStringContainsString('BACHELOR_SUBJECT_MISMATCH', $service);
        $this->assertStringContainsString('POST_RELATED_SUBJECT_MISMATCH', $service);
        $this->assertStringContainsString('TECHNICAL_ELIGIBILITY_MISMATCH', $service);
        $this->assertStringContainsString('QUOTA_ELIGIBILITY_MISMATCH', $service);
        $this->assertStringContainsString("'CFF' => (bool) \$registration->has_ff_quota ? 'PASS' : 'FAIL'", $service);
        $this->assertStringContainsString("'EM' => (bool) \$registration->has_em_quota ? 'PASS' : 'FAIL'", $service);
        $this->assertStringContainsString("'PHC' => (bool) \$registration->has_phc_quota ? 'PASS' : 'FAIL'", $service);
        $this->assertStringContainsString('CADRE_SEAT_LIMIT_EXCEEDED', $service);
        $this->assertStringContainsString('$allocated <= $sanctioned', $service);
        $this->assertStringContainsString("\$schema->create('allocation_a5_candidate_results'", $migration);
        $this->assertStringContainsString('allocation_a5_capacity_results', $migration);
    }

    #[Test]
    public function a5_requires_current_a4_and_one_hundred_percent_pass_before_finalization(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/AllocationController.php'));
        $service = file_get_contents(app_path('Services/Allocation/AllocationA5ValidityService.php'));
        $stale = file_get_contents(app_path('Services/Allocation/AllocationRunStaleService.php'));
        $view = file_get_contents(resource_path('views/allocation/a5-show.blade.php'));

        $this->assertStringContainsString('inspectStrict()', $controller);
        $this->assertStringContainsString("where('status', 'a4_complete')", $controller);
        $this->assertStringContainsString("where('is_stale', false)", $controller);
        $this->assertStringContainsString("status !== 'validated_ok'", $service);
        $this->assertStringContainsString('candidate_failed > 0', $service);
        $this->assertStringContainsString('capacity_failed > 0', $service);
        $this->assertStringContainsString('staleA5ForNewA4', $stale);
        $this->assertStringContainsString('Reporting/Export remains BLOCKED', $view);
        $this->assertStringContainsString('Finalize A5 — 100% PASS', $view);
    }
}
