<?php

namespace Tests\Feature\Merit;

use Tests\TestCase;

final class MeritRegistrationSubjectDisplayContractTest extends TestCase
{
    public function test_individual_merit_registration_displays_subject_code_and_title(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/MeritController.php'));
        $show = file_get_contents(resource_path('views/merit/show.blade.php'));
        $pdf = file_get_contents(resource_path('views/reports/pdf/merit-individual.blade.php'));

        $this->assertStringContainsString('BachelorSubject::query()', $controller);
        $this->assertStringContainsString('PostRelatedSubject::query()', $controller);
        $this->assertStringContainsString('RegistrationReferencePresenter::codeTitle', $controller);
        $this->assertStringContainsString('Bachelor Subject', $show);
        $this->assertStringContainsString('$bachelorSubjectDisplay', $show);
        $this->assertStringContainsString('Post Related Subject (PRS)', $show);
        $this->assertStringContainsString('$postRelatedSubjectDisplay', $show);
        $this->assertStringContainsString('$bachelorSubjectDisplay', $pdf);
        $this->assertStringContainsString('$postRelatedSubjectDisplay', $pdf);
    }
}
