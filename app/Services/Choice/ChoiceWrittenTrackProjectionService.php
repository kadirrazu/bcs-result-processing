<?php

namespace App\Services\Choice;

use App\Enums\CadreType;
use App\Models\CircularEntry;
use Illuminate\Support\Collection;

/**
 * Final Allocation-ready projection for the candidate's surviving Written track.
 *
 * Choice Validation deliberately preserves otherwise-valid preferences even when
 * the corresponding Written side was not survived. Historical Optimization is
 * allowed to see that validated preference lineup first; this service then removes
 * track-incompatible choices as the final allocation-readiness projection.
 */
final class ChoiceWrittenTrackProjectionService
{
    public const REASON_CODE = 'WRITTEN_TRACK_NOT_ALLOWED_FOR_ALLOCATION';

    /**
     * @param array<int,string|int> $codes
     * @param Collection<int,CircularEntry>|array<int,CircularEntry> $circularEntries
     * @return array{input:array<int,string>,final:array<int,string>,removed:array<int,string>,details:array<int,array<string,mixed>>}
     */
    public function project(array $codes, ?string $writtenTrack, Collection|array $circularEntries): array
    {
        $input = array_values(array_filter(
            array_map(static fn ($code): string => trim((string) $code), $codes),
            static fn (string $code): bool => $code !== '',
        ));

        $entries = $circularEntries instanceof Collection
            ? $circularEntries
            : collect($circularEntries);

        $entryByCode = $entries
            ->filter(fn ($entry): bool => $entry instanceof CircularEntry && filled($entry->effective_code))
            ->keyBy(fn (CircularEntry $entry): string => (string) $entry->effective_code);

        $track = $this->normalizeTrack($writtenTrack);
        $final = [];
        $removed = [];
        $details = [];

        foreach ($input as $index => $code) {
            /** @var CircularEntry|null $entry */
            $entry = $entryByCode->get((string) $code);
            if (! $entry) {
                // This should already be impossible after Choice Validation. Keep it here
                // so this projection never becomes a second/hidden Circular validator.
                $final[] = $code;
                continue;
            }

            $type = $entry->cadre_type instanceof CadreType
                ? $entry->cadre_type->value
                : strtoupper(trim((string) $entry->cadre_type));

            if ($this->trackAllows($track, $type)) {
                $final[] = $code;
                continue;
            }

            $removed[] = $code;
            $details[] = [
                'choice_position' => $index + 1,
                'choice_code' => $code,
                'cadre_type' => $type,
                'written_qualified_track' => strtoupper(trim((string) ($writtenTrack ?? ''))),
                'effective_track' => $track,
                'reason_code' => self::REASON_CODE,
                'reason_message' => $type === 'GG'
                    ? 'Removed from Allocation-ready Choice because the candidate did not survive the General Written track.'
                    : 'Removed from Allocation-ready Choice because the candidate did not survive the Technical Written track.',
            ];
        }

        return [
            'input' => $input,
            'final' => array_values($final),
            'removed' => array_values($removed),
            'details' => array_values($details),
        ];
    }

    public function allows(?string $writtenTrack, string $cadreType): bool
    {
        return $this->trackAllows($this->normalizeTrack($writtenTrack), strtoupper(trim($cadreType)));
    }

    private function normalizeTrack(?string $writtenTrack): ?string
    {
        $value = strtoupper(trim((string) ($writtenTrack ?? '')));

        return match ($value) {
            'GG', 'GN', 'GENERAL' => 'general',
            'TT', 'T', 'TECHNICAL' => 'technical',
            'GT', 'BOTH' => 'both',
            default => null,
        };
    }

    private function trackAllows(?string $track, string $cadreType): bool
    {
        return match ($cadreType) {
            'GG' => in_array($track, ['general', 'both'], true),
            'TT' => in_array($track, ['technical', 'both'], true),
            default => false,
        };
    }
}
