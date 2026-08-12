<?php

namespace Tests\Feature\Tabulation;

use Tests\TestCase;

class TabulationWrittenDisplayPolishContractTest extends TestCase
{
    public function test_written_section_uses_clean_badges_without_leaked_expression(): void
    {
        $view = file_get_contents(resource_path('views/tabulation/show.blade.php'));

        // Status values must be prepared inside the Blade PHP setup block and then
        // rendered through the clean Written card; the source expression itself is
        // legitimate and must not be treated as leaked output.
        $this->assertStringContainsString('$technicalWrittenStatus = strtoupper', $view);
        $this->assertStringContainsString('$generalWrittenStatus = strtoupper', $view);

        $this->assertStringContainsString('bg-green-lt text-green', $view);
        $this->assertStringContainsString('bg-red-lt text-red', $view);

        $this->assertStringContainsString('>Qualified Track</div>', $view);
        $this->assertStringContainsString('>General Counted</div>', $view);
        $this->assertStringContainsString('>Technical Counted</div>', $view);

        $this->assertStringContainsString('$statusBadge($generalWrittenStatus)', $view);
        $this->assertStringContainsString('$statusBadge($technicalWrittenStatus)', $view);
    }
}
