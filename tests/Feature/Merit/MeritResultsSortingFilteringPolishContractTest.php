<?php

namespace Tests\Feature\Merit;

use Tests\TestCase;

final class MeritResultsSortingFilteringPolishContractTest extends TestCase
{
    public function test_results_support_rank_sort_composite_track_filters_category_and_compact_all_merit_tech(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/MeritController.php'));
        $view = file_get_contents(resource_path('views/merit/results.blade.php'));

        foreach ([
            'common_merit_position',
            'general_merit_position',
            'technical_merit_position',
        ] as $field) {
            $this->assertStringContainsString("'{$field}'", $controller);
            $this->assertStringContainsString('value="'.$field.'"', $view);
        }

        $this->assertStringContainsString(
            "in_array(\$sortDirection, ['asc', 'desc'], true)",
            $controller
        );
        $this->assertStringContainsString(
            "\$q->orderByRaw(\$qualifiedSortColumn.' IS NULL')",
            $controller
        );
        $this->assertStringContainsString(
            "->orderBy(\$qualifiedSortColumn, \$sortDirection)",
            $controller
        );

        $this->assertStringContainsString(
            "\$track === 'GG_GN'",
            $controller
        );
        $this->assertStringContainsString(
            "whereIn('merit_results.written_qualified_track', ['GG', 'GN'])",
            $controller
        );
        $this->assertStringContainsString(
            "\$track === 'TT_T'",
            $controller
        );
        $this->assertStringContainsString(
            "whereIn('merit_results.written_qualified_track', ['TT', 'T'])",
            $controller
        );
        $this->assertStringContainsString('>GG + GN</option>', $view);
        $this->assertStringContainsString('>TT + T</option>', $view);

        $this->assertStringContainsString(
            "'registration_lookup.cadre_category as original_cadre_category'",
            $controller
        );
        $this->assertStringContainsString(
            '<th class="text-center">Category</th>',
            $view
        );
        $this->assertStringContainsString(
            '$categoryCode($r->original_cadre_category)',
            $view
        );

        $this->assertStringContainsString(
            'overflow-wrap: anywhere;',
            $view
        );
        $this->assertStringContainsString(
            'max-width:160px;',
            str_replace(' ', '', $view)
        );
        $this->assertStringContainsString(
            '<td colspan="10"',
            $view
        );
    }
}
