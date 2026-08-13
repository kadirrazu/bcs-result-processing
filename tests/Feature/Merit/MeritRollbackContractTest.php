<?php

namespace Tests\Feature\Merit;

use Tests\TestCase;

final class MeritRollbackContractTest extends TestCase
{
    public function test_merit_rollback_is_hash_and_source_snapshot_guarded(): void
    {
        $service = file_get_contents(app_path('Services/Merit/MeritRollbackService.php'));
        $routes = file_get_contents(base_path('routes/merit.php'));
        $controller = file_get_contents(app_path('Http/Controllers/MeritController.php'));
        $index = file_get_contents(resource_path('views/merit/index.blade.php'));

        $this->assertStringContainsString("confirmation !== 'ROLLBACK'", $service);
        $this->assertStringContainsString('MERIT_ROLLBACK_SOURCE_MISMATCH', $service);
        $this->assertStringContainsString('MERIT_ROLLBACK_DATASET_HASH_MISMATCH', $service);
        $this->assertStringContainsString('$this->readiness->assertReady()', $service);
        $this->assertStringContainsString('$this->hasher->hash($run->id)', $service);
        $this->assertStringContainsString("MERIT_ROLLED_BACK", $service);
        $this->assertStringContainsString("name('rollback')", $routes);
        $this->assertStringContainsString('MeritRollbackService $service', $controller);
        $this->assertStringContainsString('Finalization History', $index);
        $this->assertStringContainsString("route('merit.rollback'", $index);
    }
}
