<?php

namespace Tests\Feature\Tabulation;

use Tests\TestCase;

class TabulationIndividualStandardLayoutContractTest extends TestCase
{
    public function test_upstream_cards_use_compact_equal_height_label_value_layout(): void
    {
        $view = file_get_contents(resource_path('views/tabulation/show.blade.php'));
        $upstream = substr($view, 0, strpos($view, 'Source → Derived Verification'));

        $this->assertGreaterThanOrEqual(4, substr_count($upstream, 'col-lg-6 d-flex'));
        $this->assertGreaterThanOrEqual(4, substr_count($upstream, 'card w-100 h-100'));
        $this->assertStringContainsString('list-group list-group-flush', $upstream);
        $this->assertStringContainsString('col-6 text-secondary', $upstream);
        $this->assertStringContainsString('col-6 fw-medium', $upstream);
        $this->assertStringNotContainsString('table table-sm table-vcenter mb-0', $upstream);
    }
}
