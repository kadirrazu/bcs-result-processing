<?php

namespace Tests\Feature\Merit;

use Tests\TestCase;

final class MeritLandingClosureRegressionContractTest extends TestCase
{
    public function test_landing_preserves_rollback_history_and_generate_regenerate_semantics(): void
    {
        $view = file_get_contents(resource_path('views/merit/index.blade.php'));

        $this->assertStringContainsString('Finalization History', $view);
        $this->assertStringContainsString("route('merit.rollback'", $view);
        $this->assertStringContainsString('ROLLBACK', $view);

        $this->assertStringContainsString(
            "\$meritGenerateLabel = \$hasGeneratedMerit ? 'Regenerate Merit' : 'Generate Merit';",
            $view
        );
        $this->assertStringContainsString('Merit Generation in Progress', $view);
        $this->assertStringContainsString('Regenerate Merit?', $view);
        $this->assertStringContainsString("route('merit.generate')", $view);
        $this->assertStringContainsString('{{ $meritGenerateLabel }}', $view);

        $this->assertStringContainsString('Recent Audit', $view);
    }
}
