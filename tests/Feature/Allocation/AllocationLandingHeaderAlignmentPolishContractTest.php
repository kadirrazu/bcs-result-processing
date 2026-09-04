<?php

namespace Tests\Feature\Allocation;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AllocationLandingHeaderAlignmentPolishContractTest extends TestCase
{
    #[Test]
    public function centered_allocation_data_columns_have_matching_centered_headings(): void
    {
        $view = file_get_contents(resource_path('views/allocation/index.blade.php'));

        foreach (['Version', 'Rows', 'Total', 'MQ', 'CFF', 'EM', 'PHC', 'Candidates', 'Choice Ready', 'Queue Entries'] as $heading) {
            $this->assertMatchesRegularExpression(
                '/<th[^>]*class="[^"]*text-center[^"]*"[^>]*>\s*'.preg_quote($heading, '/').'\s*<\/th>/',
                $view,
                "Expected {$heading} heading to be center aligned."
            );
        }
    }
}
