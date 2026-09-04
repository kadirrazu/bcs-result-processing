<?php

namespace App\Services\ChoiceOptimization;

use App\Models\ChoiceOptimizationEffectiveChoice;
use App\Models\ChoiceValidationResult;
use App\Services\ChoiceValidation\ChoiceValidationFinalizedDatasetService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class ChoiceOptimizationHistoricalInputService
{
    public function __construct(
        private readonly ChoiceValidationFinalizedDatasetService $choiceValidation,
    ) {}

    /**
     * Lightweight provenance/readiness information for operator pages.
     *
     * Historical Optimization may consume the OMR-approved Effective Choice only when
     * that snapshot is bound to the CURRENT finalized Choice Validation version. A stale
     * Effective Choice containing a Viva OMR override must be revalidated/re-approved;
     * silently carrying that override across a new Circular/Choice Validation authority
     * could produce a wrong Allocation-ready choice.
     *
     * @return array<string,mixed>
     */
    public function bindingSummary(): array
    {
        $summary = $this->choiceValidation->summary();
        $currentVersion = (int) ($summary['validation_version'] ?? 0);

        if (! ($summary['ready'] ?? false) || $currentVersion <= 0) {
            return [
                'can_process' => false,
                'use_effective' => false,
                'status' => 'CHOICE_VALIDATION_NOT_READY',
                'status_label' => 'CHOICE VALIDATION NOT READY',
                'message' => 'A current finalized Choice Validation dataset is required.',
                'current_choice_validation_version' => $currentVersion,
                'effective_rows' => (int) ChoiceOptimizationEffectiveChoice::query()->count(),
                'effective_override_rows' => 0,
                'effective_choice_validation_versions' => [],
            ];
        }

        $currentChoiceCount = ChoiceValidationResult::query()
            ->where('validation_version', $currentVersion)
            ->where('status', 'valid')
            ->where('validated_choice_count', '>', 0)
            ->count();

        return $this->bindingAgainstVersion($currentVersion, $currentChoiceCount);
    }

    /**
     * Strict server-side gate used before queueing Historical Optimization.
     * This also verifies the finalized Choice Validation dataset hash before accepting it.
     *
     * @return array<string,mixed>
     */
    public function assertReadyForOptimization(): array
    {
        $summary = $this->choiceValidation->verifiedSummary();
        $currentVersion = (int) ($summary['validation_version'] ?? 0);
        $currentChoiceCount = ChoiceValidationResult::query()
            ->where('validation_version', $currentVersion)
            ->where('status', 'valid')
            ->where('validated_choice_count', '>', 0)
            ->count();

        $binding = $this->bindingAgainstVersion($currentVersion, $currentChoiceCount);
        if (! (bool) $binding['can_process']) {
            throw new RuntimeException((string) $binding['message']);
        }

        return $binding;
    }

    /**
     * @return array{
     *   rows: array<int,array{registration_id:int,reg:string,source:string,codes:array<int,string>}>,
     *   source:string,
     *   source_hash:string,
     *   choice_validation_version:int,
     *   choice_validation_hash:string
     * }
     */
    public function snapshot(): array
    {
        // Strictly verify the CURRENT finalized Choice Validation authority first.
        $summary = $this->choiceValidation->verifiedSummary();
        $choiceRows = $this->choiceValidation->choiceReadyResults();
        $currentVersion = (int) ($summary['validation_version'] ?? 0);
        $binding = $this->bindingAgainstVersion($currentVersion, $choiceRows->count());

        if (! (bool) $binding['can_process']) {
            throw new RuntimeException((string) $binding['message']);
        }

        $effectiveRows = ChoiceOptimizationEffectiveChoice::query()
            ->orderBy('registration_id')
            ->get()
            ->keyBy('registration_id');

        /*
         * Only a CURRENT-bound Effective Choice may override finalized validated choices.
         * If an old Effective Choice has no OMR overrides, it is safe and preferable to
         * ignore that stale copy and consume the latest finalized Choice Validation rows
         * directly. This prevents old validated choices from leaking into a re-process.
         */
        $useEffective = (bool) $binding['use_effective'];

        $rows = [];
        foreach ($choiceRows as $choiceRow) {
            $registrationId = (int) $choiceRow->registration_id;
            $validatedCodes = $this->cleanCodes((array) $choiceRow->validated_choice_codes);

            if ($useEffective) {
                $effective = $effectiveRows->get($registrationId);
                if (! $effective) {
                    throw new RuntimeException(
                        "Effective choice row is missing for registration {$choiceRow->reg}. Re-validate and re-approve the OMR/effective-choice stage."
                    );
                }

                // Result-id equality binds this row to the exact current finalized CV row,
                // not merely to a coincidentally equal candidate count.
                if ((int) $effective->choice_validation_result_id !== (int) $choiceRow->id) {
                    throw new RuntimeException(
                        "Effective choice for registration {$choiceRow->reg} is bound to an older Choice Validation result. Re-validate and re-approve OMR before Optimization."
                    );
                }

                // Defensive row-level invariant: the validated sequence copied into the
                // Effective Choice must still equal the current finalized validated row.
                if ($this->cleanCodes((array) $effective->validated_choice_codes) !== $validatedCodes) {
                    throw new RuntimeException(
                        "Effective choice for registration {$choiceRow->reg} does not match current finalized Choice Validation. Re-validate and re-approve OMR before Optimization."
                    );
                }

                $codes = $this->cleanCodes((array) $effective->effective_choice_codes);
                $source = (string) $effective->choice_source;
            } else {
                $codes = $validatedCodes;
                $source = 'finalized_validated_choice';
            }

            $rows[] = [
                'registration_id' => $registrationId,
                'reg' => (string) $choiceRow->reg,
                'source' => $source,
                'codes' => $codes,
            ];
        }

        $sourceHash = $this->hashRows($rows);

        return [
            'rows' => $rows,
            'source' => $useEffective ? 'choice_optimization_effective_choice' : 'finalized_validated_choice',
            'source_hash' => $sourceHash,
            'choice_validation_version' => $currentVersion,
            'choice_validation_hash' => (string) ($summary['dataset_hash'] ?? ''),
        ];
    }

    /** @return array<string,mixed> */
    private function bindingAgainstVersion(int $currentVersion, int $currentChoiceCount): array
    {
        $effectiveCount = (int) ChoiceOptimizationEffectiveChoice::query()->count();
        if ($effectiveCount === 0) {
            return [
                'can_process' => true,
                'use_effective' => false,
                'status' => 'LATEST_FINALIZED_VALIDATED_CHOICE',
                'status_label' => 'LATEST FINALIZED CHOICE VALIDATION',
                'message' => "Optimization will use finalized Choice Validation v{$currentVersion} directly.",
                'current_choice_validation_version' => $currentVersion,
                'current_choice_ready_rows' => $currentChoiceCount,
                'effective_rows' => 0,
                'effective_override_rows' => 0,
                'effective_choice_validation_versions' => [],
            ];
        }

        $overrideCount = (int) ChoiceOptimizationEffectiveChoice::query()
            ->where('choice_source', 'viva_omr_override')
            ->count();

        $versionRows = DB::connection('exam')
            ->table('choice_optimization_effective_choices as effective')
            ->leftJoin('choice_validation_results as cv', 'cv.id', '=', 'effective.choice_validation_result_id')
            ->selectRaw('cv.validation_version, COUNT(*) AS row_count')
            ->groupBy('cv.validation_version')
            ->get();

        $versions = $versionRows
            ->filter(fn ($row): bool => $row->validation_version !== null)
            ->map(fn ($row): int => (int) $row->validation_version)
            ->unique()->sort()->values()->all();

        $mismatchedRows = (int) DB::connection('exam')
            ->table('choice_optimization_effective_choices as effective')
            ->leftJoin('choice_validation_results as cv', 'cv.id', '=', 'effective.choice_validation_result_id')
            ->where(function ($query) use ($currentVersion): void {
                $query->whereNull('cv.id')
                    ->orWhere('cv.validation_version', '<>', $currentVersion);
            })
            ->count();

        $isCurrentBound = $mismatchedRows === 0 && $effectiveCount === $currentChoiceCount;

        if ($isCurrentBound) {
            return [
                'can_process' => true,
                'use_effective' => true,
                'status' => 'CURRENT_EFFECTIVE_CHOICE',
                'status_label' => 'CURRENT OMR / EFFECTIVE CHOICE',
                'message' => "Effective Choice is bound to finalized Choice Validation v{$currentVersion}.",
                'current_choice_validation_version' => $currentVersion,
                'current_choice_ready_rows' => $currentChoiceCount,
                'effective_rows' => $effectiveCount,
                'effective_override_rows' => $overrideCount,
                'effective_choice_validation_versions' => $versions,
            ];
        }

        if ($overrideCount > 0) {
            $used = $versions === [] ? 'unknown' : 'v'.implode(', v', $versions);

            return [
                'can_process' => false,
                'use_effective' => false,
                'status' => 'STALE_EFFECTIVE_OVERRIDE_BLOCKED',
                'status_label' => 'STALE OMR EFFECTIVE CHOICE — BLOCKED',
                'message' => "OMR Effective Choice is stale (bound to {$used}; current finalized Choice Validation is v{$currentVersion}). Re-validate and re-approve the OMR batch against Choice Validation v{$currentVersion} before re-processing Optimization.",
                'current_choice_validation_version' => $currentVersion,
                'current_choice_ready_rows' => $currentChoiceCount,
                'effective_rows' => $effectiveCount,
                'effective_override_rows' => $overrideCount,
                'effective_choice_validation_versions' => $versions,
                'mismatched_effective_rows' => $mismatchedRows,
            ];
        }

        // A stale Effective Choice containing only unchanged validated choices carries no
        // OMR override authority. Ignore the stale copy and consume current finalized CV.
        return [
            'can_process' => true,
            'use_effective' => false,
            'status' => 'STALE_EFFECTIVE_FALLBACK_TO_LATEST_CV',
            'status_label' => 'LATEST FINALIZED CHOICE VALIDATION',
            'message' => "Old Effective Choice is not current and contains no OMR override. Optimization will use finalized Choice Validation v{$currentVersion} directly.",
            'current_choice_validation_version' => $currentVersion,
            'current_choice_ready_rows' => $currentChoiceCount,
            'effective_rows' => $effectiveCount,
            'effective_override_rows' => 0,
            'effective_choice_validation_versions' => $versions,
            'mismatched_effective_rows' => $mismatchedRows,
        ];
    }

    /** @param array<int,array{registration_id:int,reg:string,source:string,codes:array<int,string>}> $rows */
    public function hashRows(array $rows): string
    {
        $context = hash_init('sha256');

        foreach ($rows as $row) {
            hash_update($context, json_encode([
                'registration_id' => $row['registration_id'],
                'reg' => $row['reg'],
                'source' => $row['source'],
                'codes' => array_values($row['codes']),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
        }

        return hash_final($context);
    }

    /** @return array<int,string> */
    private function cleanCodes(array $codes): array
    {
        return array_values(array_filter(
            array_map(static fn ($code): string => trim((string) $code), $codes),
            static fn (string $code): bool => $code !== '',
        ));
    }
}
