<?php

namespace Tests\Feature\ChoiceValidation;

use Tests\TestCase;

final class ChoiceValidationCv6Cv7FinalizationContractTest extends TestCase
{
    public function test_finalization_schema_and_reset_registry_are_registered(): void
    {
        $migration = file_get_contents(
            database_path('examination-migrations/2026_08_12_031500_create_choice_validation_finalization.php')
        );
        $config = file_get_contents(config_path('development-module-reset.php'));

        self::assertStringContainsString('choice_validation_finalization_runs', $migration);
        self::assertStringContainsString('finalized_validation_version', $migration);
        self::assertStringContainsString('latest_finalization_run_id', $migration);
        self::assertStringContainsString("'choice_validation_finalization_runs'", $config);
    }

    public function test_finalization_blocks_incomplete_or_stale_source_and_validation(): void
    {
        $service = file_get_contents(
            app_path('Services/ChoiceValidation/ChoiceValidationFinalizationService.php')
        );

        self::assertStringContainsString("invalid_rows !== 0", $service);
        self::assertStringContainsString("status !== 'completed'", $service);
        self::assertStringContainsString('$state->is_stale', $service);
        self::assertStringContainsString('$pendingManualCorrections > 0', $service);
        self::assertStringContainsString('revalidation', strtolower($service));
        self::assertStringContainsString('result count', $service);
    }

    public function test_finalization_is_bound_to_a_dataset_hash(): void
    {
        $hasher = file_get_contents(
            app_path('Services/ChoiceValidation/ChoiceValidationDatasetHasher.php')
        );
        $service = file_get_contents(
            app_path('Services/ChoiceValidation/ChoiceValidationFinalizationService.php')
        );
        $dataset = file_get_contents(
            app_path('Services/ChoiceValidation/ChoiceValidationFinalizedDatasetService.php')
        );

        self::assertStringContainsString("hash_init('sha256')", $hasher);
        self::assertStringContainsString("'dataset_hash' => \$hash", $service);
        self::assertStringContainsString('hash_equals', $dataset);
    }

    public function test_only_current_finalized_non_stale_dataset_is_exposed_downstream(): void
    {
        $dataset = file_get_contents(
            app_path('Services/ChoiceValidation/ChoiceValidationFinalizedDatasetService.php')
        );

        self::assertStringContainsString("status === 'finalized'", $dataset);
        self::assertStringContainsString('finalized_validation_version', $dataset);
        self::assertStringContainsString('current_validation_version', $dataset);
        self::assertStringContainsString('choiceReadyResults', $dataset);
        self::assertStringContainsString('validatedChoiceMap', $dataset);
    }

    public function test_manual_or_source_correction_invalidates_finalization_pointer(): void
    {
        $manual = file_get_contents(
            app_path('Services/ChoiceValidation/ChoiceManualCorrectionService.php')
        );
        $source = file_get_contents(
            app_path('Services/ChoiceValidation/ChoiceSourceApprovalService.php')
        );

        self::assertStringContainsString("'finalized_validation_version' => null", $manual);
        self::assertStringContainsString("'latest_finalization_run_id' => null", $manual);
        self::assertStringContainsString("'finalized_at' => null", $manual);

        self::assertStringContainsString("'finalized_validation_version' => \$hadValidation ? null", $source);
    }

    public function test_routes_and_reports_expose_finalization_and_final_exports(): void
    {
        $routes = file_get_contents(base_path('routes/choice-validation.php'));
        $controller = file_get_contents(
            app_path('Http/Controllers/ChoiceValidationController.php')
        );

        self::assertStringContainsString('/finalization', $routes);
        self::assertStringContainsString('/final-report/pdf', $routes);
        self::assertStringContainsString('/final-report/excel', $routes);
        self::assertStringContainsString('finalizeValidation', $controller);
        self::assertStringContainsString('finalReportPdf', $controller);
        self::assertStringContainsString('finalReportExcel', $controller);
    }

    public function test_original_imported_choice_does_not_show_retained_text(): void
    {
        $view = file_get_contents(
            resource_path('views/choice-validation/result-detail.blade.php')
        );

        $start = strpos($view, 'Original Imported Choices');
        $end = strpos($view, 'Effective Choices After Manual Correction');

        self::assertNotFalse($start);
        self::assertNotFalse($end);

        $originalSection = substr($view, $start, $end - $start);

        self::assertStringNotContainsString('>Retained<', $originalSection);
        self::assertStringContainsString('Removed', $originalSection);
        self::assertStringContainsString('Expanded', $originalSection);
    }
}
