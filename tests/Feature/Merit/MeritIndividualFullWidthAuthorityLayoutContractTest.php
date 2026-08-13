<?php

namespace Tests\Feature\Merit;

use Tests\TestCase;

final class MeritIndividualFullWidthAuthorityLayoutContractTest extends TestCase
{
    public function test_choice_validation_and_merit_source_authority_are_full_width_sections(): void
    {
        $view = file_get_contents(resource_path('views/merit/show.blade.php'));

        $choiceStart = strpos($view, '<h3 class="card-title">Finalized Choice Validation</h3>');
        $authorityStart = strpos($view, '<h3 class="card-title">Merit Source Authority</h3>');
        $generatedStart = strpos($view, '<h3 class="card-title">Generated Merit Ranking</h3>');

        $this->assertNotFalse($choiceStart);
        $this->assertNotFalse($authorityStart);
        $this->assertNotFalse($generatedStart);
        $this->assertLessThan($authorityStart, $choiceStart);
        $this->assertLessThan($generatedStart, $authorityStart);

        $segment = substr($view, $choiceStart, $generatedStart - $choiceStart);
        $this->assertStringNotContainsString('col-lg-6', $segment);
        $this->assertStringContainsString('Finalized source versions and hashes', $segment);
        $this->assertStringContainsString('Dataset Hash', $segment);
    }
}
