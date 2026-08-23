<?php

namespace App\Services\PreviousBcsRepository;

use App\Models\PreviousBcsRepository;
use App\Models\PreviousBcsRepositoryDataset;
use Illuminate\Database\Eloquent\Collection;
use RuntimeException;

final class PreviousBcsEffectiveDatasetService
{
    public function datasetForBcs(int $bcsNumber): PreviousBcsRepositoryDataset
    {
        $repository = PreviousBcsRepository::query()
            ->with('currentEffectiveDataset')
            ->where('bcs_number', $bcsNumber)
            ->first();

        $dataset = $repository?->currentEffectiveDataset;

        if (! $dataset || $dataset->status !== 'effective' || ! $dataset->dataset_hash) {
            throw new RuntimeException("BCS {$bcsNumber} has no effective Previous BCS repository dataset.");
        }

        return $dataset;
    }

    public function effectiveBcsNumbers(): array
    {
        return PreviousBcsRepository::query()
            ->whereNotNull('current_effective_dataset_id')
            ->orderBy('bcs_number')
            ->pluck('bcs_number')
            ->map(fn ($value): int => (int) $value)
            ->all();
    }
}
