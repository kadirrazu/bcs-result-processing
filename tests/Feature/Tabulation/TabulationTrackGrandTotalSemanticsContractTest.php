<?php

namespace Tests\Feature\Tabulation;

use App\Models\TabulationResult;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class TabulationTrackGrandTotalSemanticsContractTest extends TestCase
{
    #[Test]
    public function grand_total_display_distinguishes_failed_and_non_applicable_tracks(): void
    {
        $this->assertSame('TRACK FAILED', TabulationResult::grandTotalDisplayFor('GN', 'technical', null));
        $this->assertSame('TRACK FAILED', TabulationResult::grandTotalDisplayFor('T', 'general', null));
        $this->assertSame('NOT APPLICABLE', TabulationResult::grandTotalDisplayFor('GG', 'technical', null));
        $this->assertSame('NOT APPLICABLE', TabulationResult::grandTotalDisplayFor('TT', 'general', null));
        $this->assertSame('587.00', TabulationResult::grandTotalDisplayFor('GN', 'general', 587));
        $this->assertSame('496.00', TabulationResult::grandTotalDisplayFor('T', 'technical', 496));
    }

    #[Test]
    public function generation_only_builds_grand_totals_for_surviving_tracks(): void
    {
        $source=file_get_contents(app_path('Services/Tabulation/TabulationGenerationService.php'));
        $this->assertStringContainsString("in_array(\$track,['GG','GN','GT'],true)", $source);
        $this->assertStringContainsString("in_array(\$track,['TT','T','GT'],true)", $source);
        $this->assertStringContainsString("\$generalTrackSurvives&&\$generalPf==='PASS'", $source);
        $this->assertStringContainsString("\$technicalTrackSurvives&&\$technicalPf==='PASS'", $source);
    }
}
