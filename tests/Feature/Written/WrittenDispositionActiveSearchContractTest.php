<?php

namespace Tests\Feature\Written;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class WrittenDispositionActiveSearchContractTest extends TestCase
{
    #[Test]
    public function written_disposition_listing_can_search_active_candidates_without_polluting_exports(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/WrittenController.php'));

        $this->assertStringContainsString('dispositionQuery($status, $search, true)', $controller);
        $this->assertStringContainsString('bool $includeActiveSearch = false', $controller);
        $this->assertStringContainsString('$allowActiveSearch = $includeActiveSearch && $status === \'all\' && $search !== \'\'', $controller);
        $this->assertStringContainsString('->when(! $allowActiveSearch', $controller);
        $this->assertStringContainsString("\$needle = '%'.\$search.'%'", $controller);

        // Export paths intentionally omit the third argument, so ACTIVE search rows are never exported as dispositions.
        $this->assertStringContainsString("\$rows = \$this->dispositionQuery((string) \$request->query('status', 'all'), (string) \$request->query('search', ''))->get();", $controller);
        $this->assertStringContainsString('$this->dispositionQuery($status, $search)->orderBy', $controller);
    }
}
