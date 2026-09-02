<?php

namespace Tests\Feature\Allocation;

use PHPUnit\Framework\TestCase;

class AllocationA2QueuedFreezeReviewPolishContractTest extends TestCase
{
    public function test_freeze_is_queued_and_landing_uses_json_polling(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/AllocationController.php'));
        $view = file_get_contents(resource_path('views/allocation/index.blade.php'));
        $job = file_get_contents(app_path('Jobs/ProcessAllocationInputFreeze.php'));

        $this->assertStringContainsString('ProcessAllocationInputFreeze::dispatch', $controller);
        $this->assertStringContainsString('inputFreezeStatus', $controller);
        $this->assertStringContainsString("fetch(url", $view);
        $this->assertStringContainsString('input-freeze-progress-bar', $view);
        $this->assertStringContainsString('Re-Freeze Inputs & Rebuild Queues', $view);
        $this->assertStringContainsString('implements ShouldQueue', $job);
        $this->assertStringContainsString("config('allocation.queue', 'imports')", $job);
    }

    public function test_queue_review_has_cadre_filter_compact_quota_and_non_repeated_seats(): void
    {
        $view = file_get_contents(resource_path('views/allocation/input-freeze-show.blade.php'));
        $controller = file_get_contents(app_path('Http/Controllers/AllocationController.php'));

        $this->assertStringContainsString('name="cadre_code"', $view);
        $this->assertStringContainsString('Cadre Queue Summary', $view);
        $this->assertStringContainsString('@if($q->eligible_cff)', $view);
        $this->assertStringContainsString('@if($q->eligible_em)', $view);
        $this->assertStringContainsString('@if($q->eligible_phc)', $view);
        $this->assertStringNotContainsString("CFF {{ $q->eligible_cff?'Y':'N' }}", $view);
        $this->assertStringNotContainsString('<th>Seats</th>', $view);
        $this->assertStringContainsString("where('cadre_code', (int) $cadreCode)", $controller);
        $this->assertStringContainsString('MAX(total_post) AS total_post', $controller);
    }

    public function test_progress_schema_is_available_for_queue_processing(): void
    {
        $migration = file_get_contents(database_path('examination-migrations/2026_09_02_220500_add_allocation_processing_progress.php'));
        $this->assertStringContainsString("progress_percent", $migration);
        $this->assertStringContainsString("progress_message", $migration);
        $this->assertStringContainsString("last_error", $migration);
    }
}
