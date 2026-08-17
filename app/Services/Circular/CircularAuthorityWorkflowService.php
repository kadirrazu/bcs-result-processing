<?php

namespace App\Services\Circular;

use App\Enums\CircularProcessingStatus;
use App\Models\CircularAuthorityPreview;
use App\Models\CircularConfirmation;
use App\Models\CircularEntry;
use App\Models\CircularProcessingState;
use App\Models\User;
use App\Reports\Pdf\CircularAuthorityPreviewPdfReport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final class CircularAuthorityWorkflowService
{
    public function __construct(
        private readonly CircularAuthorityPreviewPdfReport $report,
        private readonly CircularAuditService $audit,
        private readonly CircularDatasetHasher $datasetHasher,
        private readonly \App\Services\Dependencies\DownstreamStalePropagationService $downstream,
    ) {}

    public function datasetHash(int $version): string
    {
        return $this->datasetHasher->hash($version);
    }

    public function generate(User $actor): CircularAuthorityPreview
    {
        return DB::connection('exam')->transaction(function () use ($actor): CircularAuthorityPreview {
            $state = $this->state(true);
            $version = (int) $state->current_version;
            if ($version < 1 || (int) $state->approved_version !== $version || ! in_array($state->status, [CircularProcessingStatus::Approved, CircularProcessingStatus::PreviewGenerated, CircularProcessingStatus::Confirmed], true)) {
                throw ValidationException::withMessages(['preview' => 'Approve the current Circular version as effective before generating the Authority Preview.']);
            }

            $hash = $this->datasetHash($version);
            $pdf = $this->report->generate($version);
            $database = preg_replace('/[^A-Za-z0-9_.-]+/', '-', (string) config('database.connections.exam.database', 'exam')) ?: 'exam';
            $path = "circular-authority-previews/{$database}/{$pdf['filename']}";
            Storage::disk('local')->put($path, $pdf['content']);

            $preview = CircularAuthorityPreview::query()->create([
                'version' => $version,
                'dataset_hash' => $hash,
                'file_path' => $path,
                'generated_by' => $actor->id,
                'generated_at' => now(),
                'summary' => [
                    'entries' => CircularEntry::query()->where('version', $version)->count(),
                    'active_posts' => (int) CircularEntry::query()->where('version', $version)->where('status', 'active')->sum('post_count'),
                ],
            ]);

            $summary = (array) $state->summary;
            $summary['authority_preview'] = ['id' => $preview->id, 'version' => $version, 'dataset_hash' => $hash, 'generated_at' => $preview->generated_at->toIso8601String()];
            $state->update([
                'status' => CircularProcessingStatus::PreviewGenerated,
                'confirmed_version' => null,
                'confirmed_by' => null,
                'confirmed_at' => null,
                'confirmation_notes' => null,
                'finalized_version' => null,
                'finalized_by' => null,
                'finalized_at' => null,
                'summary' => $summary,
            ]);

            $this->audit->record('circular_authority_preview_generated', $actor, null, ['status', 'authority_preview'], null, ['preview_id' => $preview->id, 'version' => $version, 'dataset_hash' => $hash], ['preview_id' => $preview->id]);
            return $preview;
        });
    }

    public function confirm(CircularAuthorityPreview $preview, User $actor, string $notes): CircularConfirmation
    {
        return DB::connection('exam')->transaction(function () use ($preview, $actor, $notes): CircularConfirmation {
            $state = $this->state(true);
            $version = (int) $state->current_version;
            if ((int) $preview->version !== $version || (int) $state->approved_version !== $version) {
                throw ValidationException::withMessages(['confirmation_notes' => 'This preview does not belong to the current approved Circular version.']);
            }
            if (! Storage::disk('local')->exists($preview->file_path)) {
                throw ValidationException::withMessages(['confirmation_notes' => 'The Authority Preview PDF file is missing. Generate a new preview.']);
            }
            if (! hash_equals($preview->dataset_hash, $this->datasetHash($version))) {
                throw ValidationException::withMessages(['confirmation_notes' => 'Circular data changed after this preview was generated. Generate a new Authority Preview.']);
            }

            $confirmation = CircularConfirmation::query()->create([
                'authority_preview_id' => $preview->id,
                'version' => $version,
                'dataset_hash' => $preview->dataset_hash,
                'confirmed_by' => $actor->id,
                'confirmed_at' => now(),
                'confirmation_notes' => $notes,
            ]);

            $state->update([
                'status' => CircularProcessingStatus::Confirmed,
                'confirmed_version' => $version,
                'confirmed_by' => $actor->id,
                'confirmed_at' => now(),
                'confirmation_notes' => $notes,
                'finalized_version' => null,
                'finalized_by' => null,
                'finalized_at' => null,
            ]);

            $this->audit->record('circular_authority_preview_confirmed', $actor, $notes, ['status', 'confirmed_version'], ['status' => CircularProcessingStatus::PreviewGenerated->value], ['status' => CircularProcessingStatus::Confirmed->value, 'version' => $version], ['preview_id' => $preview->id, 'confirmation_id' => $confirmation->id, 'dataset_hash' => $preview->dataset_hash]);
            return $confirmation;
        });
    }

    public function finalize(User $actor): int
    {
        return DB::connection('exam')->transaction(function () use ($actor): int {
            $state = $this->state(true);
            $version = (int) $state->current_version;
            if ($state->status !== CircularProcessingStatus::Confirmed || (int) $state->approved_version !== $version || (int) $state->confirmed_version !== $version) {
                throw ValidationException::withMessages(['finalize' => 'Only the current approved and confirmed Circular version can be finalized.']);
            }

            $confirmation = CircularConfirmation::query()->where('version', $version)->latest('confirmed_at')->latest('id')->first();
            if (! $confirmation || ! hash_equals($confirmation->dataset_hash, $this->datasetHash($version))) {
                throw ValidationException::withMessages(['finalize' => 'The current dataset no longer matches the confirmed Authority Preview. Generate and confirm a new preview.']);
            }

            $state->update([
                'status' => CircularProcessingStatus::Finalized,
                'finalized_version' => $version,
                'finalized_by' => $actor->id,
                'finalized_at' => now(),
                'is_stale' => false,
                'stale_reason' => null,
            ]);

            $this->audit->record('circular_finalized', $actor, $confirmation->confirmation_notes, ['status', 'finalized_version'], ['status' => CircularProcessingStatus::Confirmed->value], ['status' => CircularProcessingStatus::Finalized->value, 'version' => $version], ['confirmation_id' => $confirmation->id, 'dataset_hash' => $confirmation->dataset_hash]);

            $this->downstream->propagate(
                'circular',
                'Circular v'.$version.' was finalized. Any downstream dataset generated from an older Circular version must be regenerated.',
                (int) $actor->id,
            );

            return $version;
        });
    }

    private function state(bool $lock = false): CircularProcessingState
    {
        $query = CircularProcessingState::query()->where('id', 1);
        if ($lock) { $query->lockForUpdate(); }
        return $query->firstOrCreate(['id' => 1], ['status' => CircularProcessingStatus::NotStarted, 'current_version' => 0]);
    }
}
