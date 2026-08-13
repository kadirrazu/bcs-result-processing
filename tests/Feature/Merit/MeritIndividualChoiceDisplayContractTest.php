<?php

namespace Tests\Feature\Merit;

use Tests\TestCase;

final class MeritIndividualChoiceDisplayContractTest extends TestCase
{
    public function test_individual_merit_view_shows_original_and_validated_choices_as_order_code_and_abbr_rows(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/MeritController.php'));
        $view = file_get_contents(resource_path('views/merit/show.blade.php'));

        $this->assertStringContainsString("->with(['source.items'", $controller);
        $this->assertStringContainsString('$originalChoiceCodes', $controller);
        $this->assertStringContainsString('$validatedChoiceCodes', $controller);
        $this->assertStringContainsString('CadreMaster::query()', $controller);
        $this->assertStringContainsString('CadreSubMaster::query()', $controller);
        $this->assertStringContainsString("?? 'UNKNOWN'", $controller);

        $this->assertStringContainsString('Finalized Choice Validation', $view);
        $this->assertStringContainsString('Original Choice', $view);
        $this->assertStringContainsString('Validated Choice', $view);
        $this->assertStringContainsString('>Order</th>', $view);
        $this->assertStringContainsString('>Code</th>', $view);
        $this->assertStringContainsString('>ABBR</th>', $view);

        $this->assertStringContainsString(
            '@forelse($originalChoiceCodes as $choiceIndex => $choiceCode)',
            $view
        );
        $this->assertStringContainsString(
            '@forelse($validatedChoiceCodes as $choiceIndex => $choiceCode)',
            $view
        );
        $this->assertStringContainsString('{{ $choiceIndex + 1 }}', $view);

        $this->assertStringContainsString(
            '@forelse($originalChoiceAbbrs as $choiceIndex => $choiceAbbr)',
            $view
        );
        $this->assertStringContainsString(
            '@forelse($validatedChoiceAbbrs as $choiceIndex => $choiceAbbr)',
            $view
        );

        $this->assertStringContainsString('$originalChoiceStatus($originalChoiceCodes[$choiceIndex] ?? \'\')', $view);
        $this->assertStringContainsString('$validatedChoiceStatus($validatedChoiceCodes[$choiceIndex] ?? \'\')', $view);
        $this->assertStringContainsString('table-responsive', $view);
    }
}
