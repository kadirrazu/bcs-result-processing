<?php

namespace App\Services\ChoiceOptimization;

use App\Models\ChoiceOptimizationConsolidatedHistoricalRecommendation;
use App\Models\ChoiceOptimizationGoogleFormBatch;
use App\Models\ChoiceOptimizationGoogleFormRecommendation;
use App\Models\ChoiceOptimizationHistoricalMatch;
use App\Models\ChoiceOptimizationSetting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class ChoiceOptimizationConsolidatedHistoricalRecommendationService
{
    /**
     * Rebuild the derived normalized historical-recommendation snapshot.
     * Previous BCS confirmed matches and accepted Google Form recommendations are
     * consolidated BEFORE choice trimming so optimization runs exactly once.
     */
    public function rebuild(): array
    {
        $setting = ChoiceOptimizationSetting::query()->whereKey(1)->firstOrFail();

        if ($setting->google_form_enabled === null) {
            throw new RuntimeException('Decide Google Form YES or NO before Consolidated Historical Choice Optimization.');
        }

        if ($setting->google_form_enabled) {
            $running = ChoiceOptimizationGoogleFormBatch::query()
                ->whereIn('status', [
                    'queued', 'processing', 'validation_queued', 'validating',
                    'merge_queued', 'merging',
                ])
                ->exists();

            if ($running) {
                throw new RuntimeException('A Google Form batch is still processing. Complete the current batch before Consolidated Historical Choice Optimization.');
            }
        }

        /** @var array<string,array<string,mixed>> $grouped */
        $grouped = [];

        ChoiceOptimizationHistoricalMatch::query()
            ->with('source')
            ->where('match_status', 'matched')
            ->orderBy('registration_id')
            ->orderBy('previous_bcs_number')
            ->orderBy('id')
            ->get()
            ->each(function (ChoiceOptimizationHistoricalMatch $match) use (&$grouped): void {
                $key = $this->key((int) $match->registration_id, (int) $match->previous_bcs_number);
                $cadre = $this->normalizeCadre((string) $match->previous_cadre);

                $grouped[$key] ??= [
                    'registration_id' => (int) $match->registration_id,
                    'current_reg' => (string) $match->current_reg,
                    'previous_bcs_number' => (int) $match->previous_bcs_number,
                    'cadres' => [],
                    'sources' => [],
                ];

                if ($cadre !== '') {
                    $grouped[$key]['cadres'][$cadre] = true;
                }

                $grouped[$key]['sources'][] = [
                    'source' => 'previous_bcs_repository',
                    'historical_source_id' => (int) $match->historical_source_id,
                    'repository_dataset_id' => (int) $match->repository_dataset_id,
                    'repository_row_id' => (int) $match->repository_row_id,
                    'previous_reg' => $match->previous_reg,
                    'cadre' => (string) $match->previous_cadre,
                    'resolution_status' => (string) ($match->resolution_status ?? ''),
                ];
            });

        if ($setting->google_form_enabled) {
            ChoiceOptimizationGoogleFormRecommendation::query()
                ->orderBy('registration_id')
                ->orderBy('previous_bcs_number')
                ->orderBy('id')
                ->get()
                ->each(function (ChoiceOptimizationGoogleFormRecommendation $rec) use (&$grouped): void {
                    $key = $this->key((int) $rec->registration_id, (int) $rec->previous_bcs_number);
                    $cadre = $this->normalizeCadre((string) $rec->cadre);

                    $grouped[$key] ??= [
                        'registration_id' => (int) $rec->registration_id,
                        'current_reg' => (string) $rec->current_reg,
                        'previous_bcs_number' => (int) $rec->previous_bcs_number,
                        'cadres' => [],
                        'sources' => [],
                    ];

                    if ($cadre !== '') {
                        $grouped[$key]['cadres'][$cadre] = true;
                    }

                    $grouped[$key]['sources'][] = [
                        'source' => 'google_form',
                        'recommendation_id' => (int) $rec->id,
                        'source_batch_id' => (int) $rec->source_batch_id,
                        'source_row_id' => (int) $rec->source_row_id,
                        'cadre' => (string) $rec->cadre,
                        'accepted_by' => $rec->accepted_by ? (int) $rec->accepted_by : null,
                        'accepted_at' => $rec->accepted_at?->toIso8601String(),
                    ];
                });
        }

        ksort($grouped, SORT_STRING);
        $now = now();
        $rows = [];
        $resolved = 0;
        $multiCadreKeys = 0;
        $repositorySourceRows = 0;
        $googleFormSourceRows = 0;

        foreach ($grouped as $group) {
            $cadres = array_keys((array) $group['cadres']);
            sort($cadres, SORT_STRING);

            foreach ($group['sources'] as $source) {
                if (($source['source'] ?? null) === 'google_form') {
                    $googleFormSourceRows++;
                } else {
                    $repositorySourceRows++;
                }
            }

            $resolved++;
            if (count($cadres) > 1) {
                $multiCadreKeys++;
            }

            $rows[] = [
                'registration_id' => $group['registration_id'],
                'current_reg' => $group['current_reg'],
                'previous_bcs_number' => $group['previous_bcs_number'],
                // Keep the existing schema compatible. A multi-cadre key is resolved, not a conflict.
                // The complete unique cadre set is retained in conflict_cadres for legacy-column compatibility.
                'cadre' => count($cadres) === 1 ? ($cadres[0] ?? null) : null,
                'consolidation_status' => 'resolved',
                'sources' => json_encode(array_values($group['sources']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                'conflict_cadres' => count($cadres) > 1
                    ? json_encode($cadres, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
                    : null,
                'generated_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::connection('exam')->transaction(function () use ($rows): void {
            ChoiceOptimizationConsolidatedHistoricalRecommendation::query()->delete();

            foreach (array_chunk($rows, max(100, (int) config('choice-optimization.import_chunk_size', 1000))) as $chunk) {
                DB::connection('exam')
                    ->table('choice_optimization_consolidated_historical_recommendations')
                    ->insert($chunk);
            }
        });

        return [
            'total' => count($rows),
            'resolved' => $resolved,
            'conflicts' => 0, // backward-compatible summary key; source disagreement is non-blocking
            'multi_cadre_keys' => $multiCadreKeys,
            'previous_bcs_source_rows' => $repositorySourceRows,
            'google_form_source_rows' => $googleFormSourceRows,
            'google_form_enabled' => (bool) $setting->google_form_enabled,
            'hash' => $this->snapshotHash(),
        ];
    }

    /** @return Collection<int,ChoiceOptimizationConsolidatedHistoricalRecommendation> */
    public function forRegistration(int $registrationId): Collection
    {
        return ChoiceOptimizationConsolidatedHistoricalRecommendation::query()
            ->where('registration_id', $registrationId)
            ->orderBy('previous_bcs_number')
            ->orderBy('id')
            ->get();
    }

    public function snapshotHash(): string
    {
        $context = hash_init('sha256');

        ChoiceOptimizationConsolidatedHistoricalRecommendation::query()
            ->orderBy('registration_id')
            ->orderBy('previous_bcs_number')
            ->orderBy('id')
            ->get()
            ->each(function (ChoiceOptimizationConsolidatedHistoricalRecommendation $row) use ($context): void {
                $sources = array_values((array) $row->sources);
                usort($sources, static fn (array $a, array $b): int =>
                    json_encode($a, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                    <=> json_encode($b, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                );

                $conflicts = array_values((array) $row->conflict_cadres);
                sort($conflicts, SORT_STRING);

                hash_update($context, json_encode([
                    'registration_id' => (int) $row->registration_id,
                    'current_reg' => (string) $row->current_reg,
                    'previous_bcs_number' => (int) $row->previous_bcs_number,
                    'cadre' => (string) ($row->cadre ?? ''),
                    'status' => (string) $row->consolidation_status,
                    'sources' => $sources,
                    'conflict_cadres' => $conflicts,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
            });

        return hash_final($context);
    }

    public function googleFormSnapshotHash(): string
    {
        $setting = ChoiceOptimizationSetting::query()->whereKey(1)->firstOrFail();
        $context = hash_init('sha256');

        hash_update($context, 'google_form_enabled='.(is_null($setting->google_form_enabled) ? 'null' : ((bool) $setting->google_form_enabled ? '1' : '0'))."\n");

        if ($setting->google_form_enabled) {
            ChoiceOptimizationGoogleFormRecommendation::query()
                ->orderBy('registration_id')
                ->orderBy('previous_bcs_number')
                ->orderBy('id')
                ->get()
                ->each(function (ChoiceOptimizationGoogleFormRecommendation $rec) use ($context): void {
                    hash_update($context, json_encode([
                        'id' => (int) $rec->id,
                        'registration_id' => (int) $rec->registration_id,
                        'current_reg' => (string) $rec->current_reg,
                        'previous_bcs_number' => (int) $rec->previous_bcs_number,
                        'cadre' => $this->normalizeCadre((string) $rec->cadre),
                        'source_batch_id' => (int) $rec->source_batch_id,
                        'source_row_id' => (int) $rec->source_row_id,
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
                });
        }

        return hash_final($context);
    }

    private function key(int $registrationId, int $bcs): string
    {
        return $registrationId.'|'.$bcs;
    }

    private function normalizeCadre(string $cadre): string
    {
        return mb_strtoupper(trim($cadre));
    }
}
