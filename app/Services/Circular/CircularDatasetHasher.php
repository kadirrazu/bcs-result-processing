<?php

namespace App\Services\Circular;

use App\Models\CircularEntry;

final class CircularDatasetHasher
{
    public function hash(int $version): string
    {
        $payload = CircularEntry::query()
            ->with(['bachelorSubjects', 'prsSubjects'])
            ->where('version', $version)
            ->orderBy('cadre_type')->orderBy('cadre_serial')
            ->orderByRaw('sub_serial IS NULL DESC')->orderBy('sub_serial')->orderBy('id')
            ->get()
            ->map(fn (CircularEntry $entry) => [
                'cadre_serial' => (int) $entry->cadre_serial,
                'sub_serial' => $entry->sub_serial === null ? null : (int) $entry->sub_serial,
                'cadre_code' => (int) $entry->cadre_code,
                'sub_cadre_code' => $entry->sub_cadre_code === null ? null : (int) $entry->sub_cadre_code,
                'effective_code' => (int) $entry->effective_code,
                'cadre_type' => $entry->cadre_type->value,
                'cadre_name' => $entry->cadre_name_snapshot,
                'cadre_name_bn' => $entry->cadre_name_bn_snapshot,
                'post_name' => $entry->post_name_snapshot,
                'post_name_bn' => $entry->post_name_bn_snapshot,
                'post_count' => (int) $entry->post_count,
                'status' => (string) $entry->status,
                'note' => $entry->note,
                'bachelor_subject_codes' => $entry->bachelorSubjects->pluck('subject_code')->map(fn ($v) => (string) $v)->sort()->values()->all(),
                'prs_codes' => $entry->prsSubjects->pluck('prs_code')->map(fn ($v) => (string) $v)->sort()->values()->all(),
            ])->values()->all();

        return hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }
}
