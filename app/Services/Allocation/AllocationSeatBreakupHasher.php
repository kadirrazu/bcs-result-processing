<?php

namespace App\Services\Allocation;

use App\Models\AllocationSeatBreakupVersion;

final class AllocationSeatBreakupHasher
{
    public function hash(AllocationSeatBreakupVersion $version): string
    {
        $rows = $version->rows()->orderBy('id')->get()->map(fn ($row): array => [
            'sl' => (string) $row->sl,
            'cadre_code' => (int) $row->cadre_code,
            'total_post' => (int) $row->total_post,
            'mq' => (int) $row->mq,
            'cff' => (int) $row->cff,
            'em' => (int) $row->em,
            'phc' => (int) $row->phc,
            'circular_entry_id' => (int) $row->circular_entry_id,
        ])->all();

        return hash('sha256', json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }
}
