<?php

namespace App\Services\PreviousBcsRepository;

use App\Models\PreviousBcsRepository;
use App\Models\PreviousBcsRepositoryDataset;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class PreviousBcsRepositoryAuthorityService
{
    public function __construct(
        private readonly PreviousBcsRepositoryValidationService $validation,
        private readonly PreviousBcsRepositoryAuditService $audit,
    ) {}

    public function makeEffective(int $datasetId, int $actorId): PreviousBcsRepositoryDataset
    {
        return DB::transaction(function () use ($datasetId, $actorId): PreviousBcsRepositoryDataset {
            $dataset = PreviousBcsRepositoryDataset::query()->lockForUpdate()->findOrFail($datasetId);
            $repository = PreviousBcsRepository::query()->lockForUpdate()->findOrFail($dataset->repository_id);

            if ($dataset->status !== 'validated') {
                throw new RuntimeException('Only a successfully validated dataset can become effective.');
            }

            if ((int) $dataset->invalid_rows !== 0) {
                throw new RuntimeException('A dataset containing invalid rows cannot become effective.');
            }

            if (! $dataset->dataset_hash) {
                throw new RuntimeException('Validated dataset hash is missing.');
            }

            $currentHash = $this->validation->datasetHash($dataset->id);
            if (! hash_equals((string) $dataset->dataset_hash, $currentHash)) {
                throw new RuntimeException('Dataset integrity changed after validation. Re-validation is required.');
            }

            $previousEffectiveId = $repository->current_effective_dataset_id;
            if ($previousEffectiveId && (int) $previousEffectiveId !== (int) $dataset->id) {
                PreviousBcsRepositoryDataset::query()
                    ->whereKey($previousEffectiveId)
                    ->where('status', 'effective')
                    ->update(['status' => 'superseded']);
            }

            $dataset->update([
                'status' => 'effective',
                'approved_at' => now(),
                'approved_by' => $actorId,
            ]);

            $repository->update([
                'current_effective_dataset_id' => $dataset->id,
            ]);

            $this->audit->record('DATASET_MADE_EFFECTIVE', $repository->id, $dataset->id, $actorId, [
                'bcs_number' => $repository->bcs_number,
                'version' => $dataset->version,
                'dataset_hash' => $dataset->dataset_hash,
                'previous_effective_dataset_id' => $previousEffectiveId,
            ]);

            return $dataset->refresh();
        });
    }
}
