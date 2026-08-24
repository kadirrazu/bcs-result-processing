<?php

namespace App\Services\ChoiceOptimization;

use App\Models\ChoiceOptimizationHistoricalMatch;
use App\Models\ChoiceOptimizationHistoricalSource;
use App\Models\ChoiceOptimizationProcessingAudit;
use App\Models\PreviousBcsRepositoryDataset;
use App\Models\PreviousBcsRepositoryRow;
use App\Models\Registration;
use App\Models\WrittenProcessingState;
use App\Models\WrittenResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final class ChoiceOptimizationHistoricalPullService
{
    public const MATCHING_ALGORITHM = 'co4c1-core-v1';

    public function pull(int $sourceId, int $actorId): ChoiceOptimizationHistoricalSource
    {
        $source = ChoiceOptimizationHistoricalSource::query()->findOrFail($sourceId);

        $writtenState = WrittenProcessingState::query()->first();
        if (! $writtenState?->result_finalized_at || (bool) $writtenState->is_stale) {
            throw new RuntimeException('Finalize a current, non-stale Written result before pulling Previous BCS data.');
        }

        $dataset = PreviousBcsRepositoryDataset::query()
            ->with('repository')
            ->findOrFail((int) $source->repository_dataset_id);

        if (
            $dataset->status !== 'effective'
            || ! $dataset->dataset_hash
            || ! hash_equals((string) $source->repository_dataset_hash, (string) $dataset->dataset_hash)
            || (int) $dataset->repository?->current_effective_dataset_id !== (int) $dataset->id
        ) {
            throw new RuntimeException(
                'The selected Previous BCS repository dataset is no longer the current effective version. Queue a fresh re-pull.'
            );
        }

        $source->update([
            'status' => 'pulling',
            'candidate_count' => 0,
            'matched_count' => 0,
            'review_count' => 0,
            'no_match_count' => 0,
            'matching_algorithm' => self::MATCHING_ALGORITHM,
            'failure_message' => null,
        ]);

        try {
            $historicalRows = PreviousBcsRepositoryRow::query()
                ->where('dataset_id', (int) $dataset->id)
                ->where('validation_status', 'valid')
                ->orderBy('source_row')
                ->get();

            // Convert the Eloquent collection to a base collection before groupBy().
            // Otherwise the grouped values are nested Collections and Eloquent's except()
            // tries to call getKey() on those Collections.
            $historicalByCore = $historicalRows
                ->toBase()
                ->groupBy(fn (PreviousBcsRepositoryRow $row): string => $this->historicalCoreKey($row) ?? '__NO_CORE__')
                ->except('__NO_CORE__');

            $qualifiedRegistrationIds = WrittenResult::query()
                ->where('status', 'active')
                ->whereNotNull('written_qualified_track')
                ->pluck('registration_id')
                ->filter()
                ->map(fn ($id): int => (int) $id)
                ->unique()
                ->values();

            $registrations = Registration::query()
                ->whereIn('id', $qualifiedRegistrationIds)
                ->orderBy('id')
                ->get();

            $candidateCount = $registrations->count();
            $matchedCount = 0;
            $reviewCount = 0;
            $noMatchCount = 0;
            $insertRows = [];
            $now = now();

            foreach ($registrations as $registration) {
                $coreKey = $this->currentCoreKey($registration);
                if ($coreKey === null) {
                    $noMatchCount++;
                    continue;
                }

                /** @var Collection<int,PreviousBcsRepositoryRow> $candidates */
                $candidates = collect($historicalByCore->get($coreKey, []));

                if ($candidates->isEmpty()) {
                    $noMatchCount++;
                    continue;
                }

                // Repository validation already blocks duplicate core identity.
                // Keep this defensive review branch if an older effective dataset ever contains more than one.
                if ($candidates->count() > 1) {
                    foreach ($candidates as $historical) {
                        $insertRows[] = $this->matchRow(
                            source: $source,
                            registration: $registration,
                            historical: $historical,
                            status: 'review',
                            method: 'CORE_EXACT_MULTIPLE',
                            evidence: $this->evidence($registration, $historical, true),
                            now: $now,
                        );
                    }

                    $reviewCount++;
                    continue;
                }

                /** @var PreviousBcsRepositoryRow $historical */
                $historical = $candidates->first();
                $evidence = $this->evidence($registration, $historical, false);
                $needsReview = $this->needsReview($evidence);

                $insertRows[] = $this->matchRow(
                    source: $source,
                    registration: $registration,
                    historical: $historical,
                    status: $needsReview ? 'review' : 'matched',
                    method: $needsReview ? 'CORE_EXACT_SUPPORTING_REVIEW' : 'CORE_EXACT',
                    evidence: $evidence,
                    now: $now,
                );

                $needsReview ? $reviewCount++ : $matchedCount++;
            }

            DB::connection('exam')->transaction(function () use ($source, $insertRows): void {
                ChoiceOptimizationHistoricalMatch::query()
                    ->where('historical_source_id', (int) $source->id)
                    ->delete();

                foreach (array_chunk($insertRows, 1000) as $chunk) {
                    DB::connection('exam')
                        ->table('choice_optimization_historical_matches')
                        ->insert($chunk);
                }
            });

            $source->update([
                'status' => 'pulled',
                'candidate_count' => $candidateCount,
                'matched_count' => $matchedCount,
                'review_count' => $reviewCount,
                'no_match_count' => $noMatchCount,
                'last_pulled_by' => $actorId,
                'last_pulled_at' => now(),
                'failure_message' => null,
            ]);

            ChoiceOptimizationProcessingAudit::query()->create([
                'event' => 'HISTORICAL_PULL_COMPLETED',
                'actor_id' => $actorId,
                'from_status' => 'pulling',
                'to_status' => 'pulled',
                'context' => [
                    'previous_bcs_number' => (int) $source->previous_bcs_number,
                    'repository_dataset_id' => (int) $source->repository_dataset_id,
                    'repository_dataset_version' => (int) $source->repository_dataset_version,
                    'dataset_hash' => (string) $source->repository_dataset_hash,
                    'candidate_count' => $candidateCount,
                    'matched_count' => $matchedCount,
                    'review_count' => $reviewCount,
                    'no_match_count' => $noMatchCount,
                    'matching_algorithm' => self::MATCHING_ALGORITHM,
                ],
                'created_at' => now(),
            ]);

            return $source->refresh();
        } catch (Throwable $e) {
            $source->update([
                'status' => 'failed',
                'failure_message' => mb_substr($e->getMessage(), 0, 65000),
            ]);

            ChoiceOptimizationProcessingAudit::query()->create([
                'event' => 'HISTORICAL_PULL_FAILED',
                'actor_id' => $actorId,
                'from_status' => 'pulling',
                'to_status' => 'failed',
                'context' => [
                    'previous_bcs_number' => (int) $source->previous_bcs_number,
                    'repository_dataset_id' => (int) $source->repository_dataset_id,
                    'message' => mb_substr($e->getMessage(), 0, 2000),
                ],
                'created_at' => now(),
            ]);

            throw $e;
        }
    }

    private function currentCoreKey(Registration $registration): ?string
    {
        if (! $registration->ssc_roll || ! $registration->ssc_year || ! $registration->birth_date) {
            return null;
        }

        return implode('|', [
            $this->identity($registration->ssc_roll),
            (string) (int) $registration->ssc_year,
            $registration->birth_date->format('Y-m-d'),
        ]);
    }

    private function historicalCoreKey(PreviousBcsRepositoryRow $row): ?string
    {
        if (! $row->ssc_roll || ! $row->ssc_year || ! $row->b_date) {
            return null;
        }

        return implode('|', [
            $this->identity($row->ssc_roll),
            (string) (int) $row->ssc_year,
            $row->b_date->format('Y-m-d'),
        ]);
    }

    /** @return array<string,mixed> */
    private function evidence(Registration $current, PreviousBcsRepositoryRow $historical, bool $multipleCore): array
    {
        return [
            'core' => [
                'ssc_roll' => [
                    'current' => (string) $current->ssc_roll,
                    'previous' => (string) $historical->ssc_roll,
                    'match' => $this->identity($current->ssc_roll) === $this->identity($historical->ssc_roll),
                ],
                'ssc_year' => [
                    'current' => (int) $current->ssc_year,
                    'previous' => (int) $historical->ssc_year,
                    'match' => (int) $current->ssc_year === (int) $historical->ssc_year,
                ],
                'birth_date' => [
                    'current' => $current->birth_date?->format('Y-m-d'),
                    'previous' => $historical->b_date?->format('Y-m-d'),
                    'match' => $current->birth_date?->format('Y-m-d') === $historical->b_date?->format('Y-m-d'),
                ],
            ],
            'supporting' => [
                'name' => $this->textEvidence($current->name, $historical->name),
                'father_name' => $this->textEvidence($current->father_name, $historical->fname),
                'mother_name' => $this->textEvidence($current->mother_name, $historical->mname),
                'nid' => $this->identityEvidence($current->national_id, $historical->nid_no),
                'hsc_roll' => $this->identityEvidence($current->hsc_roll, $historical->hsc_roll),
                'hsc_year' => $this->numericEvidence($current->hsc_year, $historical->hsc_year),
                'secondary_dob' => $this->dateEvidence(
                    $current->birth_date?->format('Y-m-d'),
                    $historical->dob?->format('Y-m-d')
                ),
                'district' => [
                    'current_code' => $current->district_code,
                    'previous_name' => $historical->dist_name,
                ],
            ],
            'multiple_core_candidates' => $multipleCore,
        ];
    }

    /** @param array<string,mixed> $evidence */
    private function needsReview(array $evidence): bool
    {
        if ((bool) ($evidence['multiple_core_candidates'] ?? false)) {
            return true;
        }

        $support = (array) ($evidence['supporting'] ?? []);

        if (($support['name']['status'] ?? null) !== 'exact') {
            return true;
        }

        foreach (['nid', 'hsc_roll', 'hsc_year', 'secondary_dob'] as $key) {
            if (($support[$key]['status'] ?? null) === 'mismatch') {
                return true;
            }
        }

        return false;
    }

    /** @return array{status:string,current:?string,previous:?string} */
    private function textEvidence(mixed $current, mixed $previous): array
    {
        $currentRaw = $this->nullableText($current);
        $previousRaw = $this->nullableText($previous);

        if ($currentRaw === null || $previousRaw === null) {
            return ['status' => 'not_compared', 'current' => $currentRaw, 'previous' => $previousRaw];
        }

        $a = $this->normalizedText($currentRaw);
        $b = $this->normalizedText($previousRaw);

        if ($a === $b) {
            $status = 'exact';
        } elseif ($a !== '' && $b !== '' && (str_contains($a, $b) || str_contains($b, $a))) {
            $status = 'partial';
        } else {
            $status = 'different';
        }

        return ['status' => $status, 'current' => $currentRaw, 'previous' => $previousRaw];
    }

    /** @return array{status:string,current:?string,previous:?string} */
    private function identityEvidence(mixed $current, mixed $previous): array
    {
        $a = $this->nullableText($current);
        $b = $this->nullableText($previous);

        if ($a === null || $b === null) {
            return ['status' => 'not_compared', 'current' => $a, 'previous' => $b];
        }

        return [
            'status' => $this->identity($a) === $this->identity($b) ? 'match' : 'mismatch',
            'current' => $a,
            'previous' => $b,
        ];
    }

    /** @return array{status:string,current:?int,previous:?int} */
    private function numericEvidence(mixed $current, mixed $previous): array
    {
        if ($current === null || $current === '' || $previous === null || $previous === '') {
            return [
                'status' => 'not_compared',
                'current' => $current === null || $current === '' ? null : (int) $current,
                'previous' => $previous === null || $previous === '' ? null : (int) $previous,
            ];
        }

        return [
            'status' => (int) $current === (int) $previous ? 'match' : 'mismatch',
            'current' => (int) $current,
            'previous' => (int) $previous,
        ];
    }

    /** @return array{status:string,current:?string,previous:?string} */
    private function dateEvidence(?string $current, ?string $previous): array
    {
        if ($current === null || $previous === null) {
            return ['status' => 'not_compared', 'current' => $current, 'previous' => $previous];
        }

        return [
            'status' => $current === $previous ? 'match' : 'mismatch',
            'current' => $current,
            'previous' => $previous,
        ];
    }

    /** @return array<string,mixed> */
    private function matchRow(
        ChoiceOptimizationHistoricalSource $source,
        Registration $registration,
        PreviousBcsRepositoryRow $historical,
        string $status,
        string $method,
        array $evidence,
        mixed $now,
    ): array {
        return [
            'historical_source_id' => (int) $source->id,
            'registration_id' => (int) $registration->id,
            'current_reg' => (string) $registration->reg,
            'previous_bcs_number' => (int) $source->previous_bcs_number,
            'repository_dataset_id' => (int) $source->repository_dataset_id,
            'repository_row_id' => (int) $historical->id,
            'previous_reg' => $historical->reg,
            'previous_name' => $historical->name,
            'previous_fname' => $historical->fname,
            'previous_mname' => $historical->mname,
            'previous_cadre' => $historical->cadre,
            'match_status' => $status,
            'match_method' => $method,
            'match_evidence' => json_encode($evidence, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'resolution_status' => $status === 'matched' ? 'auto_confirmed' : 'pending',
            'resolution_reason' => null,
            'resolved_by' => null,
            'resolved_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    private function identity(mixed $value): string
    {
        $text = trim((string) ($value ?? ''));
        if (preg_match('/^(\d+)\.0+$/', $text, $match) === 1) {
            return $match[1];
        }

        return $text;
    }

    private function nullableText(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));
        return $text === '' ? null : $text;
    }

    private function normalizedText(string $value): string
    {
        $value = mb_strtoupper(trim($value));
        $value = preg_replace('/[^\pL\pN]+/u', ' ', $value) ?? $value;
        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }
}
