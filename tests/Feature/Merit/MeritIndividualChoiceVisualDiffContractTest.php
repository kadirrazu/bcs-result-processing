<?php

namespace Tests\Feature\Merit;

use Tests\TestCase;

final class MeritIndividualChoiceVisualDiffContractTest extends TestCase
{
    public function test_original_and_validated_choices_use_identity_based_visual_diff(): void
    {
        $view = file_get_contents(resource_path('views/merit/show.blade.php'));

        $this->assertStringContainsString('$originalChoiceStatus = static fn', $view);
        $this->assertStringContainsString('$validatedChoiceStatus = static fn', $view);
        $this->assertStringContainsString('RETAINED', $view);
        $this->assertStringContainsString('REMOVED', $view);
        $this->assertStringContainsString('ADDED_OR_CORRECTED', $view);
        $this->assertStringContainsString('bg-green-lt text-green', $view);
        $this->assertStringContainsString('bg-red-lt text-red', $view);
        $this->assertStringContainsString('bg-yellow-lt text-yellow', $view);
        $this->assertStringContainsString('$originalChoiceStatus($originalChoiceCodes[$choiceIndex] ?? \'\')', $view);
        $this->assertStringContainsString('$validatedChoiceStatus($validatedChoiceCodes[$choiceIndex] ?? \'\')', $view);
    }
}
