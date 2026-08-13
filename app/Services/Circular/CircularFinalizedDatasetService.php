<?php

namespace App\Services\Circular;

use App\Enums\CircularProcessingStatus;
use App\Models\CircularConfirmation;
use App\Models\CircularEntry;
use App\Models\CircularProcessingState;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class CircularFinalizedDatasetService
{
    public function __construct(private readonly CircularDatasetHasher $hasher) {}

    public function state(): CircularProcessingState
    {
        return CircularProcessingState::query()->firstOrCreate(
            ['id' => 1],
            ['status' => CircularProcessingStatus::NotStarted, 'current_version' => 0]
        );
    }

    public function isReady(): bool
    {
        $state = $this->state();
        $version = (int) ($state->finalized_version ?? 0);

        return $state->status === CircularProcessingStatus::Finalized
            && ! (bool) $state->is_stale
            && $version > 0
            && (int) $state->current_version === $version
            && (int) $state->approved_version === $version
            && (int) $state->confirmed_version === $version;
    }

    public function finalizedVersion(): int
    {
        return (int) $this->verifiedConfirmation()->version;
    }

    public function verifiedConfirmation(): CircularConfirmation
    {
        if (! $this->isReady()) {
            throw ValidationException::withMessages([
                'circular' => 'A finalized, confirmed, current and non-stale Circular version is required before this dataset can be consumed downstream.',
            ]);
        }

        $version = (int) $this->state()->finalized_version;
        $confirmation = CircularConfirmation::query()
            ->where('version', $version)
            ->latest('confirmed_at')
            ->latest('id')
            ->first();

        if (! $confirmation) {
            throw ValidationException::withMessages([
                'circular' => 'Circular confirmation record could not be resolved for the finalized version.',
            ]);
        }

        $currentHash = $this->hasher->hash($version);
        if (! hash_equals((string) $confirmation->dataset_hash, $currentHash)) {
            throw ValidationException::withMessages([
                'circular' => 'CIRCULAR_DATASET_HASH_MISMATCH: Finalized Circular data no longer matches the confirmed dataset hash. Generate, confirm and finalize a new Circular version.',
            ]);
        }

        return $confirmation;
    }

    /** @return Collection<int, CircularEntry> */
    public function entries(): Collection
    {
        $version = $this->finalizedVersion();

        return CircularEntry::query()
            ->with(['bachelorSubjects', 'prsSubjects'])
            ->where('version', $version)
            ->orderBy('cadre_type')
            ->orderBy('cadre_serial')
            ->orderByRaw('sub_serial IS NULL DESC')
            ->orderBy('sub_serial')
            ->orderBy('id')
            ->get();
    }

    /** @return array<string, mixed> */
    public function verifiedSummary(): array
    {
        $confirmation = $this->verifiedConfirmation();
        $summary = $this->summary();
        $summary['dataset_hash'] = (string) $confirmation->dataset_hash;
        $summary['hash_verified'] = true;

        return $summary;
    }

    /** @return array<string, mixed> */
    public function summary(): array
    {
        $state = $this->state();
        $ready = $this->isReady();
        $version = $ready ? (int) $state->finalized_version : (int) $state->current_version;

        $query = CircularEntry::query()->where('version', $version);
        $active = (clone $query)->where('status', 'active');

        $latestConfirmation = $ready
            ? CircularConfirmation::query()->where('version', $version)->latest('confirmed_at')->latest('id')->first()
            : null;

        return [
            'ready' => $ready,
            'version' => $version,
            'status' => $state->status,
            'entry_count' => (clone $query)->count(),
            'active_entry_count' => (clone $active)->count(),
            'general_entry_count' => (clone $active)->where('cadre_type', 'GG')->count(),
            'technical_entry_count' => (clone $active)->where('cadre_type', 'TT')->count(),
            'general_posts' => (int) (clone $active)->where('cadre_type', 'GG')->sum('post_count'),
            'technical_posts' => (int) (clone $active)->where('cadre_type', 'TT')->sum('post_count'),
            'total_posts' => (int) (clone $active)->sum('post_count'),
            'dataset_hash' => $latestConfirmation?->dataset_hash,
            'confirmed_at' => $state->confirmed_at,
            'finalized_at' => $state->finalized_at,
            'confirmation_notes' => $latestConfirmation?->confirmation_notes ?? $state->confirmation_notes,
        ];
    }
}
