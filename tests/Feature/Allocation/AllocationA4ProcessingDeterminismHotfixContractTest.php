<?php

namespace Tests\Feature\Allocation;

use Tests\TestCase;

final class AllocationA4ProcessingDeterminismHotfixContractTest extends TestCase
{
    public function test_a4_has_own_processing_page_landing_polling_and_canonical_movement_replay(): void
    {
        $service = file_get_contents(app_path('Services/Allocation/AllocationA4Service.php'));
        $routes = file_get_contents(base_path('routes/allocation.php'));
        $controller = file_get_contents(app_path('Http/Controllers/AllocationController.php'));
        $landing = file_get_contents(resource_path('views/allocation/index.blade.php'));
        $processing = file_get_contents(resource_path('views/allocation/a4-processing.blade.php'));

        $this->assertStringContainsString('$replay = $this->solve($source, null, true);', $service);
        $this->assertStringContainsString('/a4/runs/{a4Run}/processing', $routes);
        $this->assertStringContainsString('showA4Processing', $controller);
        $this->assertStringContainsString("route('allocation.a4.processing', \$a4Run)", $controller);
        $this->assertStringContainsString('a4-landing-progress-wrap', $landing);
        $this->assertStringContainsString('pollA4()', $landing);
        $this->assertStringContainsString('Dedicated NM + Shifting processing screen', $processing);
        $this->assertStringContainsString("route('allocation.a4.status', \$a4Run)", $processing);
    }
}
