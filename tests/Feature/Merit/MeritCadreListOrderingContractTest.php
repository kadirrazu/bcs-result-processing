<?php

namespace Tests\Feature\Merit;

use Tests\TestCase;

final class MeritCadreListOrderingContractTest extends TestCase
{
    public function test_cadre_wise_lists_are_sorted_by_code_and_use_code_abbreviation_count_label(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/MeritController.php'));
        $results = file_get_contents(resource_path('views/merit/results.blade.php'));
        $cadre = file_get_contents(resource_path('views/merit/cadre.blade.php'));

        $this->assertStringContainsString("->orderBy('cadre_code')", $controller);
        $this->assertStringNotContainsString("->orderBy('cadre_type')->orderBy('cadre_code')", $controller);
        $this->assertStringContainsString('{{ $c->cadre_code }} ({{ $c->cadre_abbr }}) - {{ number_format($c->candidate_count) }}', $results);
        $this->assertStringContainsString('{{ $meta->cadre_code }} ({{ $meta->cadre_abbr }}) Merit List', $cadre);
        $this->assertStringContainsString('ordered by cadre merit serial', $cadre);
    }
}
