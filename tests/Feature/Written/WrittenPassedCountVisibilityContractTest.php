<?php

namespace Tests\Feature\Written;

use Tests\TestCase;

final class WrittenPassedCountVisibilityContractTest extends TestCase
{
    public function test_written_landing_and_results_expose_passed_count(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/WrittenController.php'));
        $landing = file_get_contents(resource_path('views/written/index.blade.php'));
        $results = file_get_contents(resource_path('views/written/results.blade.php'));

        // Landing count deliberately excludes candidates cancelled at Registration as well as Written.
        $this->assertMatchesRegularExpression("/'passed'\\s*=>\\s*DB::connection\\('exam'\\)->table\\('written_results as w'\\).*?where\\('w.status',\\s*'active'\\).*?where\\('r.status',\\s*'active'\\).*?whereNotNull\\('w.written_qualified_track'\\)->count\\(\\)/s", $controller);
        $this->assertStringContainsString("'passedCount' => WrittenResult::query()->where('status', 'active')->whereNotNull('written_qualified_track')->count()", $controller);
        $this->assertStringContainsString("'passed'=>'Written Passed'", $landing);
        $this->assertStringContainsString('number_format($passedCount)', $results);
    }
}
