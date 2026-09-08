<?php

namespace Tests\Feature\Examinations;

use Tests\TestCase;

final class ExaminationReportingMetadataContractTest extends TestCase
{
    public function test_optional_reporting_metadata_and_ui_order_are_present(): void
    {
        $migration = file_get_contents(database_path('migrations/2026_09_08_220000_add_reporting_metadata_to_examinations_table.php'));
        $form = file_get_contents(resource_path('views/examinations/_form.blade.php'));
        $model = file_get_contents(app_path('Models/Examination.php'));

        foreach (['bcs_type', 'advertisement_date', 'age_calculation_date', 'is_completed'] as $field) {
            self::assertStringContainsString($field, $migration);
            self::assertStringContainsString($field, $model);
            self::assertStringContainsString($field, $form);
        }

        self::assertLessThan(strpos($form, 'age_calculation_date'), strpos($form, 'advertisement_date'));
        self::assertLessThan(strpos($form, 'is_completed'), strpos($form, 'age_calculation_date'));
        self::assertStringNotContainsString('Special BCS processing differences will be implemented in a future phase.', $form);
        self::assertStringContainsString("'nullable', Rule::enum(ExaminationType::class)", file_get_contents(app_path('Http/Requests/StoreExaminationRequest.php')));
    }
}
