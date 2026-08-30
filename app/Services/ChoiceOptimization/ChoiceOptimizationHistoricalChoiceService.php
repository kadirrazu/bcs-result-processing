<?php

namespace App\Services\ChoiceOptimization;

use App\Models\CadreMaster;
use App\Models\CadreSubMaster;
use App\Models\ChoiceOptimizationConsolidatedHistoricalRecommendation;
use App\Models\ChoiceOptimizationHistoricalChoice;
use App\Models\ChoiceOptimizationHistoricalMatch;
use App\Models\ChoiceOptimizationHistoricalSource;
use App\Models\ChoiceOptimizationProcessingAudit;
use App\Models\ChoiceOptimizationProcessingState;
use App\Models\PreviousBcsRepository;
use App\Services\Circular\CircularFinalizedDatasetService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final class ChoiceOptimizationHistoricalChoiceService
{
    public function __construct(
        private readonly ChoiceOptimizationHistoricalInputService $input,
        private readonly CircularFinalizedDatasetService $circular,
        private readonly ChoiceOptimizationConsolidatedHistoricalRecommendationService $consolidated,
    ) {}

    public function process(int $actorId): ChoiceOptimizationProcessingState
    {
        $this->assertHistoricalSourcesReady();

        $pendingReview = ChoiceOptimizationHistoricalMatch::query()
            ->where('match_status', 'review')
            ->where('resolution_status', 'pending')
            ->count();

        if ($pendingReview > 0) {
            throw new RuntimeException(
                "Resolve all {$pendingReview} pending Historical Match REVIEW item(s) before Historical Choice Optimization."
            );
        }

        $consolidationSummary = $this->consolidated->rebuild();
        $inputSnapshot = $this->input->snapshot();
        $circularSummary = $this->circular->verifiedSummary();
        $circularEntries = $this->circular->entries()
            ->where('status', 'active')
            ->values();

        $circularCodes = $circularEntries
            ->pluck('effective_code')
            ->filter()
            ->map(fn ($code): int => (int) $code)
            ->unique()
            ->flip();

        $historicalMatches = ChoiceOptimizationConsolidatedHistoricalRecommendation::query()
            ->orderBy('registration_id')
            ->orderBy('previous_bcs_number')
            ->orderBy('id')
            ->get()
            ->groupBy('registration_id');

        $masterMap = $this->historicalCadreMap();

        $state = ChoiceOptimizationProcessingState::query()
            ->firstOrCreate(['id' => 1], ['status' => 'not_started']);

        $fromStatus = (string) $state->status;
        $state->update([
            'status' => 'historical_optimizing',
            'is_stale' => true,
            'stale_reason' => 'Historical Choice Optimization is being regenerated.',
            'dataset_hash' => null,
            'finalized_by' => null,
            'finalized_at' => null,
        ]);

        try {
            $rows = [];
            $optimizedCount = 0;
            $unchangedCount = 0;
            $emptyCount = 0;
            $blockingCount = 0;
            $warningCount = 0;
            $now = now();

            foreach ($inputSnapshot['rows'] as $inputRow) {
                $registrationId = (int) $inputRow['registration_id'];
                $inputCodes = array_values($inputRow['codes']);
                $matches = collect($historicalMatches->get($registrationId, []));

                $result = $this->optimizeCandidate(
                    $inputCodes,
                    $matches,
                    $masterMap,
                    $circularCodes,
                );

                match ($result['status']) {
                    'OPTIMIZED' => $optimizedCount++,
                    'NO_HIGHER_CHOICE_REMAINS' => $emptyCount++,
                    default => $unchangedCount++,
                };

                if ($result['blocking_issues'] !== []) {
                    $blockingCount++;
                }
                if ($result['warnings'] !== []) {
                    $warningCount++;
                }

                $rows[] = [
                    'registration_id' => $registrationId,
                    'reg' => (string) $inputRow['reg'],
                    'input_choice_source' => (string) $inputRow['source'],
                    'input_choice_codes' => json_encode($inputCodes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                    'historical_recommendations' => $result['recommendations'] === [] ? null : json_encode($result['recommendations'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                    'matched_cutoff' => $result['cutoff'] === null ? null : json_encode($result['cutoff'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                    'removed_choice_codes' => $result['removed'] === [] ? null : json_encode($result['removed'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                    'final_choice_codes' => json_encode($result['final'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                    'optimization_status' => $result['status'],
                    'warnings' => $result['warnings'] === [] ? null : json_encode($result['warnings'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                    'blocking_issues' => $result['blocking_issues'] === [] ? null : json_encode($result['blocking_issues'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                    'processed_by' => $actorId,
                    'processed_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            $historicalHash = $this->historicalSnapshotHash();
            $googleFormHash = $this->consolidated->googleFormSnapshotHash();
            $consolidatedHash = $this->consolidated->snapshotHash();
            $outputHash = $this->hashOutputRows($rows);
            $sourceSnapshot = [
                'input_choice_source' => $inputSnapshot['source'],
                'input_choice_hash' => $inputSnapshot['source_hash'],
                'choice_validation_version' => $inputSnapshot['choice_validation_version'],
                'choice_validation_hash' => $inputSnapshot['choice_validation_hash'],
                'circular_version' => (int) ($circularSummary['version'] ?? 0),
                'circular_hash' => (string) ($circularSummary['dataset_hash'] ?? ''),
                'historical_snapshot_hash' => $historicalHash,
                'google_form_snapshot_hash' => $googleFormHash,
                'consolidated_historical_hash' => $consolidatedHash,
            ];

            DB::connection('exam')->transaction(function () use (
                $rows,
                $state,
                $actorId,
                $fromStatus,
                $optimizedCount,
                $unchangedCount,
                $emptyCount,
                $blockingCount,
                $warningCount,
                $consolidationSummary,
                $sourceSnapshot,
                $outputHash,
                $now,
            ): void {
                ChoiceOptimizationHistoricalChoice::query()->delete();

                foreach (array_chunk($rows, max(100, (int) config('choice-optimization.import_chunk_size', 1000))) as $chunk) {
                    DB::connection('exam')
                        ->table('choice_optimization_historical_choices')
                        ->insert($chunk);
                }

                $status = $blockingCount > 0
                    ? 'historical_optimized_with_blocking'
                    : 'historical_optimized';

                $state->update([
                    'status' => $status,
                    'is_stale' => false,
                    'stale_reason' => null,
                    'source_snapshot' => $sourceSnapshot,
                    'dataset_hash' => $outputHash,
                    'summary' => array_merge((array) $state->summary, [
                        'historical_choice_optimization' => [
                            'total_candidates' => count($rows),
                            'optimized_candidates' => $optimizedCount,
                            'unchanged_candidates' => $unchangedCount,
                            'no_higher_choice_candidates' => $emptyCount,
                            'blocking_candidates' => $blockingCount,
                            'warning_candidates' => $warningCount,
                            'consolidated_recommendations' => (int) $consolidationSummary['total'],
                            'consolidated_multi_cadre_keys' => (int) ($consolidationSummary['multi_cadre_keys'] ?? 0),
                            'previous_bcs_source_rows' => (int) $consolidationSummary['previous_bcs_source_rows'],
                            'google_form_source_rows' => (int) $consolidationSummary['google_form_source_rows'],
                            'google_form_enabled' => (bool) $consolidationSummary['google_form_enabled'],
                            'processed_at' => $now->toIso8601String(),
                        ],
                    ]),
                    'finalized_by' => null,
                    'finalized_at' => null,
                ]);

                ChoiceOptimizationProcessingAudit::query()->create([
                    'event' => 'HISTORICAL_CHOICE_OPTIMIZATION_COMPLETED',
                    'actor_id' => $actorId,
                    'from_status' => $fromStatus,
                    'to_status' => $status,
                    'context' => [
                        'total_candidates' => count($rows),
                        'optimized_candidates' => $optimizedCount,
                        'unchanged_candidates' => $unchangedCount,
                        'no_higher_choice_candidates' => $emptyCount,
                        'blocking_candidates' => $blockingCount,
                        'warning_candidates' => $warningCount,
                        'consolidated_recommendations' => (int) $consolidationSummary['total'],
                        'consolidated_multi_cadre_keys' => (int) ($consolidationSummary['multi_cadre_keys'] ?? 0),
                        'dataset_hash' => $outputHash,
                        'source_snapshot' => $sourceSnapshot,
                    ],
                    'created_at' => $now,
                ]);
            });

            return $state->refresh();
        } catch (Throwable $e) {
            $state->update([
                'status' => 'historical_optimization_failed',
                'is_stale' => true,
                'stale_reason' => $e->getMessage(),
            ]);

            ChoiceOptimizationProcessingAudit::query()->create([
                'event' => 'HISTORICAL_CHOICE_OPTIMIZATION_FAILED',
                'actor_id' => $actorId,
                'from_status' => 'historical_optimizing',
                'to_status' => 'historical_optimization_failed',
                'context' => ['message' => mb_substr($e->getMessage(), 0, 2000)],
                'created_at' => now(),
            ]);

            throw $e;
        }
    }

    /**
     * @param array<int,string> $inputCodes
     * @param Collection<int,ChoiceOptimizationConsolidatedHistoricalRecommendation> $matches
     * @param array<string,array<int,array{type:string,code:int}>> $masterMap
     * @param Collection<int,int> $circularCodes
     * @return array<string,mixed>
     */
    private function optimizeCandidate(
        array $inputCodes,
        Collection $matches,
        array $masterMap,
        Collection $circularCodes,
    ): array {
        $warnings = [];
        $blocking = [];
        $recommendations = [];
        $cutoffCandidates = [];

        foreach ($matches as $match) {
            $sources = array_values((array) $match->sources);

            // Source disagreement is intentionally non-blocking. Each unique cadre supplied by
            // Previous BCS Repository and/or Google Form is evaluated independently, and the
            // earliest matching current-choice position wins the single optimization pass.
            $cadres = collect($sources)
                ->map(fn (array $source): string => trim((string) ($source['cadre'] ?? '')))
                ->filter(fn (string $cadre): bool => $cadre !== '')
                ->unique(fn (string $cadre): string => mb_strtoupper($cadre))
                ->values();

            if ($cadres->isEmpty() && filled($match->cadre)) {
                $cadres = collect([trim((string) $match->cadre)]);
            }

            foreach ($cadres as $abbr) {
                $normalizedAbbr = mb_strtoupper(trim((string) $abbr));
                $cadreSources = collect($sources)
                    ->filter(fn (array $source): bool => mb_strtoupper(trim((string) ($source['cadre'] ?? ''))) === $normalizedAbbr)
                    ->values()
                    ->all();
                $repositorySource = collect($cadreSources)
                    ->first(fn (array $source): bool => ($source['source'] ?? null) === 'previous_bcs_repository');
                $previousReg = is_array($repositorySource) ? ($repositorySource['previous_reg'] ?? null) : null;

                $recommendations[] = [
                    'bcs_number' => (int) $match->previous_bcs_number,
                    'previous_reg' => $previousReg,
                    'cadre' => (string) $abbr,
                    'consolidation_status' => 'resolved',
                    'sources' => $cadreSources,
                ];

                $mappings = $normalizedAbbr !== '' ? ($masterMap[$normalizedAbbr] ?? []) : [];

            if ($mappings === []) {
                $warnings[] = [
                    'code' => 'UNRESOLVED_HISTORICAL_CADRE',
                    'message' => "Historical cadre abbreviation {$abbr} cannot be resolved in the current Cadre/Sub-Cadre Master. Choice remains unchanged for this recommendation.",
                    'bcs_number' => (int) $match->previous_bcs_number,
                    'cadre' => $abbr,
                ];
                continue;
            }

            $distinctCodes = collect($mappings)->pluck('code')->unique()->values();
            if ($distinctCodes->count() > 1) {
                $blocking[] = [
                    'code' => 'AMBIGUOUS_HISTORICAL_CADRE_MAPPING',
                    'message' => "Historical cadre abbreviation {$abbr} resolves to more than one current cadre/sub-cadre code. Automatic trimming is blocked.",
                    'bcs_number' => (int) $match->previous_bcs_number,
                    'cadre' => $abbr,
                    'candidate_codes' => $distinctCodes->all(),
                ];
                continue;
            }

            $code = (int) $distinctCodes->first();

            if (! $circularCodes->has($code)) {
                $warnings[] = [
                    'code' => 'HISTORICAL_CADRE_NOT_IN_CURRENT_CIRCULAR',
                    'message' => "Historical cadre {$abbr} ({$code}) is not an active exact cadre/sub-cadre code in the finalized current Circular. No trimming is applied for this recommendation.",
                    'bcs_number' => (int) $match->previous_bcs_number,
                    'cadre' => $abbr,
                    'resolved_code' => $code,
                ];
                continue;
            }

            $index = $this->choiceIndex($inputCodes, (string) $code);
            if ($index === null) {
                $warnings[] = [
                    'code' => 'NO_MATCHING_CURRENT_CHOICE',
                    'message' => "Historical cadre {$abbr} ({$code}) is not present in the current effective choice sequence.",
                    'bcs_number' => (int) $match->previous_bcs_number,
                    'cadre' => $abbr,
                    'resolved_code' => $code,
                ];
                continue;
            }

                $cutoffCandidates[] = [
                    'choice_index' => $index,
                    'choice_position' => $index + 1,
                    'choice_code' => (string) $inputCodes[$index],
                    'historical_cadre' => (string) $abbr,
                    'historical_bcs_number' => (int) $match->previous_bcs_number,
                    'previous_reg' => $previousReg,
                ];
            }
        }

        if ($blocking !== []) {
            return [
                'status' => 'UNCHANGED',
                'recommendations' => $recommendations,
                'cutoff' => null,
                'removed' => [],
                'final' => $inputCodes,
                'warnings' => $warnings,
                'blocking_issues' => $blocking,
            ];
        }

        if ($cutoffCandidates === []) {
            return [
                'status' => 'UNCHANGED',
                'recommendations' => $recommendations,
                'cutoff' => null,
                'removed' => [],
                'final' => $inputCodes,
                'warnings' => $warnings,
                'blocking_issues' => [],
            ];
        }

        usort($cutoffCandidates, static fn (array $a, array $b): int =>
            $a['choice_index'] <=> $b['choice_index']
        );

        $cutoff = $cutoffCandidates[0];
        $cutoffIndex = (int) $cutoff['choice_index'];
        $final = array_slice($inputCodes, 0, $cutoffIndex);
        $removed = array_slice($inputCodes, $cutoffIndex);

        return [
            'status' => $final === [] ? 'NO_HIGHER_CHOICE_REMAINS' : 'OPTIMIZED',
            'recommendations' => $recommendations,
            'cutoff' => $cutoff,
            'removed' => $removed,
            'final' => array_values($final),
            'warnings' => $warnings,
            'blocking_issues' => [],
        ];
    }

    private function choiceIndex(array $codes, string $needle): ?int
    {
        foreach ($codes as $index => $code) {
            if ((int) $code === (int) $needle) {
                return (int) $index;
            }
        }

        return null;
    }

    /** @return array<string,array<int,array{type:string,code:int}>> */
    private function historicalCadreMap(): array
    {
        $map = [];

        foreach (CadreMaster::query()->get(['cadre_code', 'cadre_abbr']) as $cadre) {
            $abbr = mb_strtoupper(trim((string) $cadre->cadre_abbr));
            if ($abbr === '') {
                continue;
            }
            $map[$abbr][] = ['type' => 'main', 'code' => (int) $cadre->cadre_code];
        }

        foreach (CadreSubMaster::query()->get(['sub_cadre_code', 'sub_cadre_abbr']) as $subCadre) {
            $abbr = mb_strtoupper(trim((string) $subCadre->sub_cadre_abbr));
            if ($abbr === '') {
                continue;
            }
            $map[$abbr][] = ['type' => 'sub', 'code' => (int) $subCadre->sub_cadre_code];
        }

        return $map;
    }

    public function historicalSnapshotHash(): string
    {
        $context = hash_init('sha256');

        ChoiceOptimizationHistoricalSource::query()
            ->orderBy('previous_bcs_number')
            ->get()
            ->each(function (ChoiceOptimizationHistoricalSource $source) use ($context): void {
                hash_update($context, json_encode([
                    'source_id' => (int) $source->id,
                    'bcs_number' => (int) $source->previous_bcs_number,
                    'repository_dataset_id' => (int) $source->repository_dataset_id,
                    'repository_dataset_version' => (int) $source->repository_dataset_version,
                    'repository_dataset_hash' => (string) $source->repository_dataset_hash,
                    'status' => (string) $source->status,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
            });

        ChoiceOptimizationHistoricalMatch::query()
            ->whereIn('match_status', ['matched', 'review', 'rejected'])
            ->orderBy('historical_source_id')
            ->orderBy('registration_id')
            ->orderBy('id')
            ->get()
            ->each(function (ChoiceOptimizationHistoricalMatch $match) use ($context): void {
                hash_update($context, json_encode([
                    'id' => (int) $match->id,
                    'source_id' => (int) $match->historical_source_id,
                    'registration_id' => (int) $match->registration_id,
                    'repository_row_id' => (int) $match->repository_row_id,
                    'match_status' => (string) $match->match_status,
                    'resolution_status' => (string) ($match->resolution_status ?? ''),
                    'previous_bcs_number' => (int) $match->previous_bcs_number,
                    'previous_reg' => (string) ($match->previous_reg ?? ''),
                    'previous_cadre' => (string) ($match->previous_cadre ?? ''),
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
            });

        return hash_final($context);
    }

    public function outputHashFromDatabase(): string
    {
        $context = hash_init('sha256');

        ChoiceOptimizationHistoricalChoice::query()
            ->orderBy('registration_id')
            ->get()
            ->each(function (ChoiceOptimizationHistoricalChoice $row) use ($context): void {
                hash_update(
                    $context,
                    json_encode(
                        $this->canonicalOutputRow([
                            'registration_id' => $row->registration_id,
                            'reg' => $row->reg,
                            'input_choice_source' => $row->input_choice_source,
                            'input_choice_codes' => $row->input_choice_codes,
                            'historical_recommendations' => $row->historical_recommendations,
                            'matched_cutoff' => $row->matched_cutoff,
                            'removed_choice_codes' => $row->removed_choice_codes,
                            'final_choice_codes' => $row->final_choice_codes,
                            'optimization_status' => $row->optimization_status,
                            'warnings' => $row->warnings,
                            'blocking_issues' => $row->blocking_issues,
                        ]),
                        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
                    )."\n"
                );
            });

        return hash_final($context);
    }

    /** @param array<int,array<string,mixed>> $rows */
    private function hashOutputRows(array $rows): string
    {
        $context = hash_init('sha256');

        // Finalization re-reads output ordered by registration_id.
        // Processing-time hashing must use the same canonical row order.
        usort($rows, static fn (array $a, array $b): int =>
            ((int) ($a['registration_id'] ?? 0)) <=> ((int) ($b['registration_id'] ?? 0))
        );

        foreach ($rows as $row) {
            hash_update(
                $context,
                json_encode(
                    $this->canonicalOutputRow([
                        'registration_id' => $row['registration_id'],
                        'reg' => $row['reg'],
                        'input_choice_source' => $row['input_choice_source'],
                        'input_choice_codes' => $this->decodeJsonValue($row['input_choice_codes']),
                        'historical_recommendations' => $this->decodeJsonValue($row['historical_recommendations']),
                        'matched_cutoff' => $this->decodeJsonValue($row['matched_cutoff']),
                        'removed_choice_codes' => $this->decodeJsonValue($row['removed_choice_codes']),
                        'final_choice_codes' => $this->decodeJsonValue($row['final_choice_codes']),
                        'optimization_status' => $row['optimization_status'],
                        'warnings' => $this->decodeJsonValue($row['warnings']),
                        'blocking_issues' => $this->decodeJsonValue($row['blocking_issues']),
                    ]),
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
                )."\n"
            );
        }

        return hash_final($context);
    }

    /** @return array<string,mixed> */
    private function canonicalOutputRow(array $row): array
    {
        $canonical = [
            'registration_id' => (int) ($row['registration_id'] ?? 0),
            'reg' => (string) ($row['reg'] ?? ''),
            'input_choice_source' => (string) ($row['input_choice_source'] ?? ''),
            'input_choice_codes' => array_values((array) ($row['input_choice_codes'] ?? [])),
            'historical_recommendations' => array_values((array) ($row['historical_recommendations'] ?? [])),
            'matched_cutoff' => $row['matched_cutoff'] ?: null,
            'removed_choice_codes' => array_values((array) ($row['removed_choice_codes'] ?? [])),
            'final_choice_codes' => array_values((array) ($row['final_choice_codes'] ?? [])),
            'optimization_status' => (string) ($row['optimization_status'] ?? ''),
            'warnings' => array_values((array) ($row['warnings'] ?? [])),
            'blocking_issues' => array_values((array) ($row['blocking_issues'] ?? [])),
        ];

        /** @var array<string,mixed> $result */
        $result = $this->canonicalizeValue($canonical);

        return $result;
    }

    /**
     * Canonicalize nested JSON-like values deterministically.
     *
     * MySQL JSON may normalize/reorder object keys. Hashing raw PHP associative-array
     * insertion order therefore makes a processing-time hash differ from the same
     * logical value after a database round-trip.
     *
     * Lists preserve order (choice sequence and recommendation sequence are meaningful).
     * Associative objects sort keys recursively.
     */
    private function canonicalizeValue(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(
                fn (mixed $item): mixed => $this->canonicalizeValue($item),
                $value,
            );
        }

        ksort($value, SORT_STRING);

        foreach ($value as $key => $item) {
            $value[$key] = $this->canonicalizeValue($item);
        }

        return $value;
    }

    private function decodeJsonValue(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_array($value)) {
            return $value;
        }

        return json_decode((string) $value, true, 512, JSON_THROW_ON_ERROR);
    }

    private function assertHistoricalSourcesReady(): void
    {
        $sources = ChoiceOptimizationHistoricalSource::query()
            ->orderBy('previous_bcs_number')
            ->get();

        foreach ($sources as $source) {
            if ((string) $source->status !== 'pulled') {
                throw new RuntimeException(
                    "Historical source BCS {$source->previous_bcs_number} is not in PULLED state. Complete/re-pull it before Historical Choice Optimization."
                );
            }

            $repository = PreviousBcsRepository::query()
                ->with('currentEffectiveDataset')
                ->where('bcs_number', (int) $source->previous_bcs_number)
                ->first();

            if (
                ! $repository?->currentEffectiveDataset
                || (int) $repository->currentEffectiveDataset->id !== (int) $source->repository_dataset_id
                || ! hash_equals(
                    (string) $repository->currentEffectiveDataset->dataset_hash,
                    (string) $source->repository_dataset_hash,
                )
            ) {
                throw new RuntimeException(
                    "Historical source BCS {$source->previous_bcs_number} has a newer/different EFFECTIVE repository version. Re-pull it before Historical Choice Optimization."
                );
            }
        }
    }
}
