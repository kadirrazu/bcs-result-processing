<?php
namespace Tests\Feature\Written;
use Tests\TestCase;
final class WrittenPassedCountVisibilityContractTest extends TestCase
{
    public function test_written_landing_and_results_expose_passed_count(): void
    {
        $controller=file_get_contents(app_path('Http/Controllers/WrittenController.php'));
        $landing=file_get_contents(resource_path('views/written/index.blade.php'));
        $results=file_get_contents(resource_path('views/written/results.blade.php'));
        $this->assertStringContainsString("'passed' => WrittenResult::query()->where('status', 'active')->whereNotNull('written_qualified_track')->count()",$controller);
        $this->assertStringContainsString("'passedCount' => WrittenResult::query()->where('status', 'active')->whereNotNull('written_qualified_track')->count()",$controller);
        $this->assertStringContainsString("'passed'=>'Written Passed'",$landing);
        $this->assertStringContainsString('number_format($passedCount)',$results);
    }
}
