<?php

namespace Tests\Feature\Merit;

use Tests\TestCase;

final class MeritGenerateRegenerateUiContractTest extends TestCase
{
    public function test_merit_landing_distinguishes_generate_regenerate_and_in_progress_states(): void
    {
        $view = file_get_contents(resource_path('views/merit/index.blade.php'));

        $this->assertStringContainsString(
            "\$meritGenerateLabel = \$hasGeneratedMerit ? 'Regenerate Merit' : 'Generate Merit';",
            $view
        );
        $this->assertStringContainsString(
            "in_array(strtolower((string) \$latestRun->status), ['queued', 'running'], true)",
            $view
        );
        $this->assertStringContainsString('Merit Generation in Progress', $view);
        $this->assertStringContainsString('Regenerate Merit?', $view);
        $this->assertStringContainsString('A new Merit processing version will be created.', $view);
        $this->assertStringContainsString("route('merit.generate')", $view);
        $this->assertStringContainsString('{{ $meritGenerateLabel }}', $view);
    }
}
