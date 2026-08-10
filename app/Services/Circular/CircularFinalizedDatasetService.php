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
            && $version > 0
            && (int) $state->current_version === $version
            && (int) $state->approved_version === $version
            && (int) $state->confirmed_version === $version;
    }

    public function finalizedVersion(): int
    {
        if (! $this->isReady()) {
            throw ValidationException::withMessages([
                'circular' => 'A finalized, confirmed and current Circular version is required before this dataset can be consumed downstream.',
            ]);
        }

        return (int) $this->state()->finalized_version;
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
            'confirmed_at' => $state->confirmed_at,
            'finalized_at' => $state->finalized_at,
            'confirmation_notes' => $latestConfirmation?->confirmation_notes ?? $state->confirmation_notes,
        ];
    }
}
