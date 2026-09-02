<?php

namespace Tests\Feature\Allocation;

use PHPUnit\Framework\TestCase;

class AllocationA2QueueReviewDrilldownContractTest extends TestCase
{
    public function test_frozen_input_page_supports_candidate_lookup_circular_serial_and_filtered_count(): void
    {
        $view = file_get_contents(resource_path('views/allocation/input-freeze-show.blade.php'));

        $this->assertStringContainsString('Candidate Queue Lookup', $view);
        $this->assertStringContainsString('name="reg"', $view);
        $this->assertStringContainsString("number_format(\$row['queue_count'])", $view);
        $this->assertStringContainsString("implode(', ', \$row['cadres'])", $view);
        $this->assertStringContainsString("\$entry?->cadre_serial", $view);
        $this->assertStringContainsString('View Queue', $view);
        $this->assertStringContainsString('Filtered queue entries:', $view);
        $this->assertStringContainsString("(\$queues->firstItem() ?? 1) + \$loop->index", $view);
    }

    public function test_dedicated_cadre_queue_has_search_filters_count_and_numeric_serial(): void
    {
        $view = file_get_contents(resource_path('views/allocation/input-freeze-cadre-queue.blade.php'));
        $routes = file_get_contents(base_path('routes/allocation.php'));
        $controller = file_get_contents(app_path('Http/Controllers/AllocationController.php'));

        $this->assertStringContainsString('input-freeze.cadre-queue', $routes);
        $this->assertStringContainsString('showCadreQueue', $controller);
        $this->assertStringContainsString('Full Queue Size', $view);
        $this->assertStringContainsString('Filtered Entries', $view);
        $this->assertStringContainsString('Registration / Roll', $view);
        $this->assertStringContainsString('name="quota"', $view);
        $this->assertStringContainsString("(\$queues->firstItem() ?? 1) + \$loop->index", $view);
    }
}
