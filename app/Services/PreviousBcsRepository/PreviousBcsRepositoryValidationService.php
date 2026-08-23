<?php

namespace App\Services\PreviousBcsRepository;

use App\Models\CadreMaster;
use App\Models\CadreSubMaster;
use App\Models\PreviousBcsRepositoryDataset;
use App\Models\PreviousBcsRepositoryRow;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final class PreviousBcsRepositoryValidationService
{
    public function __construct(
        private readonly PreviousBcsRepositoryAuditService $audit,
    ) {}

    public function validate(int $datasetId, int $actorId): PreviousBcsRepositoryDataset
    {
        $dataset = PreviousBcsRepositoryDataset::query()->with('repository')->findOrFail($datasetId);

        if (! in_array($dataset->status, ['validation_queued', 'staged', 'validation_failed', 'validated'], true)) {
            throw new RuntimeException("Dataset status {$dataset->status} cannot be validated.");
        }

        $dataset->update([
            'status' => 'validating',
            'processed_rows' => 0,
            'valid_rows' => 0,
            'invalid_rows' => 0,
            'progress_percent' => 0,
            'failure_message' => null,
            'dataset_hash' => null,
            'validated_at' => null,
            'validated_by' => null,
            'finished_at' => null,
        ]);

        $this->audit->record('DATASET_VALIDATION_STARTED', $dataset->repository_id, $dataset->id, $actorId);

        try {
            $mainCadres = CadreMaster::query()
                ->get(['id', 'cadre_abbr'])
                ->mapWithKeys(fn (CadreMaster $cadre): array => [
                    strtoupper(trim((string) $cadre->cadre_abbr)) => [
                        'type' => 'main',
                        'id' => (int) $cadre->id,
                    ],
                ]);

            $subCadres = CadreSubMaster::query()
                ->get(['id', 'sub_cadre_abbr'])
                ->mapWithKeys(fn (CadreSubMaster $cadre): array => [
                    strtoupper(trim((string) $cadre->sub_cadre_abbr)) => [
                        'type' => 'sub',
                        'id' => (int) $cadre->id,
                    ],
                ]);

            $knownCadres = $mainCadres->merge($subCadres);
            $duplicateRegs = $this->duplicateCounts($datasetId, 'reg');
            $duplicateCoreIdentity = $this->duplicateCoreIdentityCounts($datasetId);

            $total = (int) $dataset->rows()->count();
            $processed = 0;
            $valid = 0;
            $invalid = 0;

            $dataset->rows()
                ->orderBy('id')
                ->chunkById(1000, function ($rows) use (
                    $dataset,
                    $actorId,
                    $knownCadres,
                    $duplicateRegs,
                    $duplicateCoreIdentity,
                    $total,
                    &$processed,
                    &$valid,
                    &$invalid,
                ): void {
                    DB::transaction(function () use (
                        $rows,
                        $dataset,
                        $knownCadres,
                        $duplicateRegs,
                        $duplicateCoreIdentity,
                        &$processed,
                        &$valid,
                        &$invalid,
                    ): void {
                        foreach ($rows as $row) {
                            $errors = [];
                            $warnings = [];

                            if ($row->validation_status === 'invalid_source') {
                                foreach ((array) $row->validation_errors as $error) {
                                    $errors[] = $error;
                                }
                            }

                            $reg = trim((string) ($row->reg ?? ''));
                            if ($reg !== '' && (($duplicateRegs[$reg] ?? 0) > 1)) {
                                $errors[] = [
                                    'field' => 'reg',
                                    'code' => 'DUPLICATE_PREVIOUS_BCS_REG',
                                    'message' => "Registration {$reg} occurs more than once in this BCS dataset.",
                                ];
                            }

                            $coreKey = $this->coreIdentityKey($row);
                            if ($coreKey !== null && (($duplicateCoreIdentity[$coreKey] ?? 0) > 1)) {
                                $errors[] = [
                                    'field' => 'identity',
                                    'code' => 'DUPLICATE_CORE_IDENTITY',
                                    'message' => 'The same SSC roll + SSC year + primary DOB occurs more than once in this BCS dataset.',
                                ];
                            }

                            if ($row->b_date && $row->dob && ! $row->b_date->isSameDay($row->dob)) {
                                $warnings[] = [
                                    'field' => 'dob',
                                    'code' => 'SECONDARY_DOB_MISMATCH',
                                    'message' => 'Optional secondary DOB does not match primary b_date. b_date remains the primary matching authority.',
                                ];
                            }

                            $cadre = trim((string) ($row->cadre ?? ''));
                            $cadreLookup = strtoupper($cadre);

                            if ($cadre === '') {
                                $errors[] = [
                                    'field' => 'cadre',
                                    'code' => 'CADRE_REQUIRED',
                                    'message' => 'cadre is required.',
                                ];
                            } elseif (! $knownCadres->has($cadreLookup)) {
                                $warnings[] = [
                                    'field' => 'cadre',
                                    'code' => 'CADRE_MASTER_MISMATCH',
                                    'message' => "Historical cadre abbreviation {$cadre} was preserved, but it does not currently match the central Cadre/Sub-Cadre master namespace.",
                                ];
                            }

                            $errors = $this->uniqueMessages($errors);
                            $warnings = $this->uniqueMessages($warnings);
                            $isValid = $errors === [];

                            $row->update([
                                // Preserve the historical abbreviation as supplied in the dataset.
                                'cadre' => $cadre !== '' ? $cadre : null,
                                'validation_status' => $isValid ? 'valid' : 'invalid',
                                'validation_errors' => $errors === [] ? null : $errors,
                                'validation_warnings' => $warnings === [] ? null : $warnings,
                            ]);

                            $processed++;
                            $isValid ? $valid++ : $invalid++;
                        }
                    });

                    $dataset->update([
                        'processed_rows' => $processed,
                        'valid_rows' => $valid,
                        'invalid_rows' => $invalid,
                        'progress_percent' => $total > 0
                            ? min(99.9, round(($processed / $total) * 100, 4))
                            : 0,
                    ]);
                });

            $hash = $invalid === 0 ? $this->datasetHash($dataset->id) : null;

            $dataset->update([
                'status' => $invalid === 0 ? 'validated' : 'validation_failed',
                'processed_rows' => $processed,
                'valid_rows' => $valid,
                'invalid_rows' => $invalid,
                'progress_percent' => 100,
                'dataset_hash' => $hash,
                'validated_at' => $invalid === 0 ? now() : null,
                'validated_by' => $invalid === 0 ? $actorId : null,
                'finished_at' => now(),
            ]);

            $this->audit->record(
                $invalid === 0 ? 'DATASET_VALIDATED' : 'DATASET_VALIDATION_FAILED',
                $dataset->repository_id,
                $dataset->id,
                $actorId,
                [
                    'valid_rows' => $valid,
                    'invalid_rows' => $invalid,
                    'dataset_hash' => $hash,
                ],
            );

            return $dataset->refresh();
        } catch (Throwable $e) {
            $dataset->update([
                'status' => 'validation_failed',
                'failure_message' => mb_substr($e->getMessage(), 0, 65000),
                'finished_at' => now(),
            ]);

            $this->audit->record('DATASET_VALIDATION_EXCEPTION', $dataset->repository_id, $dataset->id, $actorId, [
                'message' => mb_substr($e->getMessage(), 0, 2000),
            ]);

            throw $e;
        }
    }

    public function datasetHash(int $datasetId): string
    {
        $context = hash_init('sha256');

        PreviousBcsRepositoryRow::query()
            ->where('dataset_id', $datasetId)
            ->where('validation_status', 'valid')
            ->orderBy('source_row')
            ->chunkById(1000, function ($rows) use ($context): void {
                foreach ($rows as $row) {
                    hash_update($context, json_encode([
                        'source_row' => (int) $row->source_row,
                        'reg' => (string) $row->reg,
                        'name' => (string) $row->name,
                        'fname' => (string) ($row->fname ?? ''),
                        'mname' => (string) ($row->mname ?? ''),
                        'b_date' => $row->b_date?->format('Y-m-d'),
                        'dob' => $row->dob?->format('Y-m-d'),
                        'dist_name' => (string) ($row->dist_name ?? ''),
                        'ssc_roll' => (string) $row->ssc_roll,
                        'ssc_year' => (int) $row->ssc_year,
                        'hsc_roll' => (string) $row->hsc_roll,
                        'hsc_year' => (int) $row->hsc_year,
                        'nid_no' => (string) ($row->nid_no ?? ''),
                        'cadre' => (string) $row->cadre,
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                    hash_update($context, "\n");
                }
            });

        return hash_final($context);
    }

    /** @return array<string,int> */
    private function duplicateCounts(int $datasetId, string $column): array
    {
        return PreviousBcsRepositoryRow::query()
            ->where('dataset_id', $datasetId)
            ->whereNotNull($column)
            ->where($column, '<>', '')
            ->selectRaw("{$column} as duplicate_key, COUNT(*) as aggregate")
            ->groupBy($column)
            ->havingRaw('COUNT(*) > 1')
            ->pluck('aggregate', 'duplicate_key')
            ->map(fn ($value): int => (int) $value)
            ->all();
    }

    /** @return array<string,int> */
    private function duplicateCoreIdentityCounts(int $datasetId): array
    {
        return PreviousBcsRepositoryRow::query()
            ->where('dataset_id', $datasetId)
            ->whereNotNull('ssc_roll')
            ->whereNotNull('ssc_year')
            ->whereNotNull('b_date')
            ->selectRaw("CONCAT(ssc_roll, '|', ssc_year, '|', DATE_FORMAT(b_date, '%Y-%m-%d')) as duplicate_key, COUNT(*) as aggregate")
            ->groupBy('ssc_roll', 'ssc_year', 'b_date')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('aggregate', 'duplicate_key')
            ->map(fn ($value): int => (int) $value)
            ->all();
    }

    private function coreIdentityKey(PreviousBcsRepositoryRow $row): ?string
    {
        if (! $row->ssc_roll || ! $row->ssc_year || ! $row->b_date) {
            return null;
        }

        return implode('|', [
            (string) $row->ssc_roll,
            (string) $row->ssc_year,
            $row->b_date->format('Y-m-d'),
        ]);
    }

    /** @param list<array<string,mixed>> $messages
     *  @return list<array<string,mixed>>
     */
    private function uniqueMessages(array $messages): array
    {
        $seen = [];
        $result = [];

        foreach ($messages as $message) {
            $key = implode('|', [
                (string) ($message['field'] ?? ''),
                (string) ($message['code'] ?? ''),
                (string) ($message['message'] ?? ''),
            ]);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $result[] = $message;
        }

        return $result;
    }
}
