<?php

namespace Tests\Feature\Circular;

use Tests\TestCase;

final class CircularC4HotfixContractTest extends TestCase
{
    public function test_c4_hotfix_contract_files_contain_required_workflow_and_ui_fixes(): void
    {
        $listing = file_get_contents(resource_path('views/circular/entries-index.blade.php'));
        $detail = file_get_contents(resource_path('views/circular/entry-show.blade.php'));
        $index = file_get_contents(resource_path('views/circular/index.blade.php'));
        $routes = file_get_contents(base_path('routes/circular.php'));
        $service = file_get_contents(app_path('Services/Circular/CircularDatasetService.php'));

        $this->assertStringNotContainsString("{{ strtoupper(\$entry->source) }}</div></td>", $listing);
        $this->assertStringContainsString('d-inline-flex gap-2', $listing);
        $this->assertStringContainsString("@if(\$entry->cadre_type->value === 'GG')", $detail);
        $this->assertStringContainsString('Approve Current Draft as Effective', $index);
        $this->assertStringContainsString("name('draft.approve')", $routes);
        $this->assertStringContainsString('approveCurrentDraft', $service);
        $this->assertStringContainsString('circular_draft_approved_as_effective', $service);
    }
}
