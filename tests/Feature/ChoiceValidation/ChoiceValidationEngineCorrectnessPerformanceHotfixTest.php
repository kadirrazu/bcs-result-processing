<?php

namespace Tests\Feature\ChoiceValidation;

use Tests\TestCase;

final class ChoiceValidationEngineCorrectnessPerformanceHotfixTest extends TestCase
{
    public function test_viva_candidate_gate_uses_finalized_processing_run_not_per_row_finalized_at(): void
    {
        $resolver = file_get_contents(app_path('Services/ChoiceValidation/ChoiceCandidateTrackResolver.php'));

        self::assertStringContainsString('VivaFinalizationRun', $resolver);
        self::assertStringContainsString("where('processing_run_id', \$this->finalizedProcessingRunId)", $resolver);
        self::assertStringNotContainsString('! $viva->finalized_at', $resolver);
        self::assertStringContainsString('resolveMany', $resolver);
    }

    public function test_choice_validation_preloads_reference_data_and_bulk_writes_chunks(): void
    {
        $engine = file_get_contents(app_path('Services/ChoiceValidation/ChoiceValidationEngine.php'));
        $service = file_get_contents(app_path('Services/ChoiceValidation/ChoiceValidationProcessingService.php'));

        self::assertStringContainsString('public function prepare', $engine);
        self::assertStringContainsString('$this->mainByCode', $engine);
        self::assertStringContainsString('$this->entriesByEffectiveCode', $engine);
        self::assertStringNotContainsString("CadreMaster::query()->where('cadre_code'", $engine);
        self::assertStringNotContainsString("CadreSubMaster::query()->where('sub_cadre_code'", $engine);

        self::assertStringContainsString('resolveMany($registrationIds)', $service);
        self::assertStringContainsString("table('choice_validation_results')->insert(\$resultRows)", $service);
        self::assertStringContainsString("table('choice_validation_items')->insert(\$itemChunk)", $service);
        self::assertStringContainsString("config('choice-validation.processing_chunk_size', 500)", $service);
    }

    public function test_results_page_uses_json_polling_progress_bar_not_meta_refresh(): void
    {
        $routes = file_get_contents(base_path('routes/choice-validation.php'));
        $view = file_get_contents(resource_path('views/choice-validation/results.blade.php'));

        self::assertStringContainsString('/runs/{run}/progress', $routes);
        self::assertStringContainsString('choice-validation-progress-card', $view);
        self::assertStringContainsString('progress-bar-striped progress-bar-animated', $view);
        self::assertStringContainsString('setInterval(poll, 1500)', $view);
        self::assertStringNotContainsString('http-equiv="refresh"', $view);
    }
}
