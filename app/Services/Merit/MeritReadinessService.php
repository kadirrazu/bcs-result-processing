<?php

namespace App\Services\Merit;

use App\Services\ChoiceValidation\ChoiceValidationFinalizedDatasetService;
use App\Services\Circular\CircularFinalizedDatasetService;
use App\Services\Tabulation\TabulationFinalizedDatasetService;
use Illuminate\Validation\ValidationException;
use Throwable;

final class MeritReadinessService
{
    public function __construct(
        private readonly CircularFinalizedDatasetService $circular,
        private readonly TabulationFinalizedDatasetService $tabulation,
        private readonly ChoiceValidationFinalizedDatasetService $choices,
    ) {}

    /** @return array<string,mixed> */
    public function inspect(): array
    {
        $checks = [];
        $snapshot = [];

        try {
            $circular = $this->circular->verifiedSummary();
            $checks['circular'] = ['ready' => true, 'label' => 'Circular', 'detail' => 'Finalized dataset hash verified.'];
            $snapshot['circular'] = ['version' => (int) $circular['version'], 'dataset_hash' => (string) $circular['dataset_hash']];
        } catch (Throwable $e) {
            $checks['circular'] = ['ready' => false, 'label' => 'Circular', 'detail' => $this->message($e)];
        }

        try {
            $tabulation = $this->tabulation->verifiedSummary();
            $checks['tabulation'] = ['ready' => true, 'label' => 'Tabulation', 'detail' => 'Finalized dataset hash verified.'];
            $snapshot['tabulation'] = [
                'processing_run_id' => (int) $tabulation['processing_run_id'],
                'processing_version' => (int) $tabulation['processing_version'],
                'dataset_hash' => (string) $tabulation['dataset_hash'],
            ];
        } catch (Throwable $e) {
            $checks['tabulation'] = ['ready' => false, 'label' => 'Tabulation', 'detail' => $this->message($e)];
        }

        try {
            $choice = $this->choices->verifiedSummary();
            $choiceCircularVersion = (int) ($choice['circular_version'] ?? 0);
            $currentCircularVersion = (int) ($snapshot['circular']['version'] ?? 0);
            if ($currentCircularVersion > 0 && $choiceCircularVersion !== $currentCircularVersion) {
                throw ValidationException::withMessages([
                    'choice_validation' => 'CHOICE_VALIDATION_CIRCULAR_VERSION_MISMATCH: Finalized Choice Validation was produced against a different Circular version.',
                ]);
            }
            $checks['choice_validation'] = ['ready' => true, 'label' => 'Choice Validation', 'detail' => 'Finalized dataset hash and Circular version verified.'];
            $snapshot['choice_validation'] = [
                'validation_version' => (int) $choice['validation_version'],
                'source_version' => (int) $choice['source_version'],
                'circular_version' => $choiceCircularVersion,
                'dataset_hash' => (string) $choice['dataset_hash'],
            ];
        } catch (Throwable $e) {
            $checks['choice_validation'] = ['ready' => false, 'label' => 'Choice Validation', 'detail' => $this->message($e)];
        }

        return [
            'ready' => collect($checks)->every(fn (array $check): bool => $check['ready']),
            'checks' => $checks,
            'source_snapshot' => $snapshot,
        ];
    }

    /** @return array<string,mixed> */
    public function assertReady(): array
    {
        $inspection = $this->inspect();
        if (! $inspection['ready']) {
            $reasons = collect($inspection['checks'])
                ->reject(fn (array $check): bool => $check['ready'])
                ->map(fn (array $check): string => $check['label'].': '.$check['detail'])
                ->implode(' | ');

            throw ValidationException::withMessages([
                'merit' => 'Merit Generation readiness failed. '.$reasons,
            ]);
        }

        return $inspection;
    }

    private function message(Throwable $e): string
    {
        if ($e instanceof ValidationException) {
            return collect($e->errors())->flatten()->first() ?? $e->getMessage();
        }

        return $e->getMessage();
    }
}
