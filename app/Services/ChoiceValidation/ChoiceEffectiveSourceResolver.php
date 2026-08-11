<?php

namespace App\Services\ChoiceValidation;

use App\Models\ChoiceSource;
use App\Models\ChoiceValidationManualCorrection;
use Illuminate\Support\Collection;

final class ChoiceEffectiveSourceResolver
{
    /** @var array<int,ChoiceValidationManualCorrection|null> */
    private array $correctionCache = [];

    public function __construct(private readonly ChoiceColumnResolver $columns) {}

    /**
     * Preload the newest correction overlay for one processing chunk.
     * This prevents one correction lookup query per candidate during full runs.
     *
     * @param iterable<int,ChoiceSource> $sources
     */
    public function preload(iterable $sources): void
    {
        $ids = collect($sources)->pluck('id')->filter()->map(fn ($id) => (int) $id)->values()->all();
        $this->correctionCache = [];

        foreach ($ids as $id) {
            $this->correctionCache[$id] = null;
        }

        if ($ids === []) {
            return;
        }

        ChoiceValidationManualCorrection::query()
            ->whereIn('choice_source_id', $ids)
            ->orderByDesc('id')
            ->get()
            ->each(function (ChoiceValidationManualCorrection $correction): void {
                $sourceId = (int) $correction->choice_source_id;
                if (($this->correctionCache[$sourceId] ?? null) === null) {
                    $this->correctionCache[$sourceId] = $correction;
                }
            });
    }

    /** @return array<string,mixed> */
    public function snapshot(ChoiceSource $source): array
    {
        $sourceId = (int) $source->id;

        if (array_key_exists($sourceId, $this->correctionCache)) {
            $correction = $this->correctionCache[$sourceId];
        } else {
            $correction = ChoiceValidationManualCorrection::query()
                ->where('choice_source_id', $sourceId)
                ->latest('id')
                ->first();
        }

        return $correction
            ? (array) $correction->corrected_snapshot
            : (array) $source->source_snapshot;
    }

    /** @return list<object{position:int,source_column:string,raw_value:mixed,choice_code:?string}> */
    public function items(ChoiceSource $source): array
    {
        $snapshot = $this->snapshot($source);
        $items = [];

        foreach ($this->columns->choiceColumns() as $index => $column) {
            $raw = $snapshot[$column] ?? null;
            $text = trim((string) ($raw ?? ''));
            if ($text === '') {
                continue;
            }

            $items[] = (object) [
                'position' => $index + 1,
                'source_column' => $column,
                'raw_value' => $raw,
                'choice_code' => $text,
            ];
        }

        return $items;
    }
}
