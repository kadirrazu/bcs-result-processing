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

    /**
     * Lightweight operator-facing readiness inspection.
     *
     * This deliberately uses stored finalized hashes instead of re-hashing every
     * dataset on each page request. Strict live hash verification remains mandatory
     * in assertReady(), which is used by Generate, queued processing and Finalize.
     *
     * @return array<string,mixed>
     */
    public function inspect(): array
    {
        return $this->buildInspection(false);
    }

    /**
     * Strict downstream gate. Recomputes all authoritative hashes before work starts.
     *
     * @return array<string,mixed>
     */
    public function assertReady(): array
    {
        $inspection = $this->buildInspection(true);
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

    /** @return array<string,mixed> */
    private function buildInspection(bool $verifyHashes): array
    {
        $checks = [];
        $snapshot = [];

        try {
            $circular = $verifyHashes
                ? $this->circular->verifiedSummary()
                : $this->circular->storedFinalizedSummary();
            $checks['circular'] = [
                'ready' => true,
                'label' => 'Circular',
                'detail' => $verifyHashes
                    ? 'Finalized dataset hash verified.'
                    : 'Finalized dataset ready. Stored hash will be re-verified before processing.',
            ];
            $snapshot['circular'] = [
                'version' => (int) $circular['version'],
                'dataset_hash' => (string) $circular['dataset_hash'],
            ];
        } catch (Throwable $e) {
            $checks['circular'] = ['ready' => false, 'label' => 'Circular', 'detail' => $this->message($e)];
        }

        try {
            $tabulation = $verifyHashes
                ? $this->tabulation->verifiedSummary()
                : $this->tabulation->storedFinalizedSummary();
            $checks['tabulation'] = [
                'ready' => true,
                'label' => 'Tabulation',
                'detail' => $verifyHashes
                    ? 'Finalized dataset hash verified.'
                    : 'Finalized dataset ready. Stored hash will be re-verified before processing.',
            ];
            $snapshot['tabulation'] = [
                'processing_run_id' => (int) $tabulation['processing_run_id'],
                'processing_version' => (int) $tabulation['processing_version'],
                'dataset_hash' => (string) $tabulation['dataset_hash'],
            ];
        } catch (Throwable $e) {
            $checks['tabulation'] = ['ready' => false, 'label' => 'Tabulation', 'detail' => $this->message($e)];
        }

        try {
            $choice = $verifyHashes
                ? $this->choices->verifiedSummary()
                : $this->choices->storedFinalizedSummary();
            $choiceCircularVersion = (int) ($choice['circular_version'] ?? 0);
            $currentCircularVersion = (int) ($snapshot['circular']['version'] ?? 0);
            if ($currentCircularVersion > 0 && $choiceCircularVersion !== $currentCircularVersion) {
                throw ValidationException::withMessages([
                    'choice_validation' => 'CHOICE_VALIDATION_CIRCULAR_VERSION_MISMATCH: Finalized Choice Validation was produced against a different Circular version.',
                ]);
            }
            $checks['choice_validation'] = [
                'ready' => true,
                'label' => 'Choice Validation',
                'detail' => $verifyHashes
                    ? 'Finalized dataset hash and Circular version verified.'
                    : 'Finalized dataset and Circular version ready. Stored hash will be re-verified before processing.',
            ];
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
            'hash_verification_mode' => $verifyHashes ? 'STRICT_LIVE_HASH_VERIFICATION' : 'STORED_FINALIZED_HASH_STATUS',
        ];
    }

    private function message(Throwable $e): string
    {
        if ($e instanceof ValidationException) {
            return collect($e->errors())->flatten()->first() ?? $e->getMessage();
        }

        return $e->getMessage();
    }
}
