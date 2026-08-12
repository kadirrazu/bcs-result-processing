<?php

namespace Tests\Feature\Registration;

use Tests\TestCase;

final class RegistrationChoiceUiPolishingContractTest extends TestCase
{
    public function test_registration_reference_fields_use_code_title_convention(): void
    {
        $presenter = file_get_contents(app_path('Support/Registrations/RegistrationReferencePresenter.php'));
        $show = file_get_contents(resource_path('views/registrations/show.blade.php'));
        $form = file_get_contents(resource_path('views/registrations/_form.blade.php'));
        $index = file_get_contents(resource_path('views/registrations/index.blade.php'));

        self::assertStringContainsString("\$codeText.' - '.", $presenter);
        self::assertStringContainsString('Bachelor subject', $show);
        self::assertStringContainsString('Post-related subject (PRS)', $show);
        self::assertStringContainsString("RegistrationReferencePresenter::codeTitle(\$registration->bachelor_subject_code", $show);
        self::assertStringContainsString("RegistrationReferencePresenter::codeTitle(\$registration->post_related_subject_code", $show);

        self::assertStringContainsString("{{ \$item->code }} - {{ \$item->name }}", $form);
        self::assertStringContainsString("{{ \$item->subject_code }} - {{ \$item->subject_name }}", $form);
        self::assertStringContainsString("{{ \$item->code }} - {{ \$item->name }}", $index);
        self::assertStringContainsString("{{ \$item->subject_code }} - {{ \$item->subject_name }}", $index);
    }

    public function test_registration_landing_has_operational_summary_cards_from_actual_registration_states(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/RegistrationController.php'));
        $view = file_get_contents(resource_path('views/registrations/index.blade.php'));

        foreach (['total', 'gg', 'tt', 'gt', 'active', 'cancelled', 'withheld', 'invalid_validation'] as $key) {
            self::assertStringContainsString("'{$key}'", $controller);
        }

        foreach (['Total Candidates', 'GG', 'TT', 'GT', 'Active', 'Cancelled', 'Withheld', 'Invalid Validation'] as $label) {
            self::assertStringContainsString($label, $view);
        }

        self::assertStringNotContainsString('Expelled', $view);
    }

    public function test_choice_source_import_area_is_single_operational_card_not_split_text_columns(): void
    {
        $view = file_get_contents(resource_path('views/choice-validation/index.blade.php'));

        self::assertStringContainsString('Choice Source Excel Import', $view);
        self::assertStringContainsString('Identity: user, reg', $view);
        self::assertStringContainsString('Minimum: 1 choice', $view);
        self::assertStringContainsString('Maximum: {{ $maximumAllowedChoices }}', $view);
        self::assertStringContainsString('d-flex flex-column flex-lg-row', $view);
        self::assertStringNotContainsString('class="row g-2 align-items-end"', $view);
    }
    public function test_registration_listing_displays_master_code_and_title_on_separate_lines(): void
    {
        $view = file_get_contents(resource_path('views/registrations/index.blade.php'));

        self::assertStringContainsString('$record->sex_code', $view);
        self::assertStringContainsString('$sexTitle', $view);
        self::assertStringContainsString('$record->district_code', $view);
        self::assertStringContainsString('$districtTitle', $view);
        self::assertStringContainsString('$record->bachelor_subject_code', $view);
        self::assertStringContainsString('$subjectTitle', $view);
        self::assertStringContainsString('$record->cadre_category->label()', $view);
        self::assertStringContainsString('text-secondary small', $view);
    }

}
