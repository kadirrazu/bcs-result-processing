<?php

namespace Tests\Feature\Tabulation;

use Tests\TestCase;

final class TabulationRecentAuditOperatorContractTest extends TestCase
{
    public function test_recent_audit_resolves_and_displays_operator_identity(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/TabulationController.php'));
        $view = file_get_contents(resource_path('views/tabulation/index.blade.php'));

        $this->assertStringContainsString("->whereIn('id',", $controller);
        $this->assertStringContainsString("audits->pluck('actor_id')->filter()->unique()->values()", $controller);
        $this->assertStringContainsString("'auditActors' =>", $controller);
        $this->assertStringContainsString('<th>Operator</th>', $view);
        $this->assertStringContainsString('{{ $actor->name }}', $view);
        $this->assertStringContainsString('{{ $actor->email }}', $view);
        $this->assertStringContainsString('System', $view);
    }
}
