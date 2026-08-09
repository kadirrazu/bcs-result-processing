<?php

namespace App\Services\Circular;

use App\Enums\CircularProcessingStatus;
use App\Models\CircularEntry;
use App\Models\CircularImportBatch;
use App\Models\CircularProcessingState;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CircularDatasetService
{
    public function __construct(
        private readonly CircularEntryValidator $validator,
        private readonly CircularAuditService $audit,
    ) {}

    public function approveImport(CircularImportBatch $batch, User $actor, ?string $note = null): int
    {
        if ($batch->invalid_rows > 0) {
            throw ValidationException::withMessages(['batch' => 'Resolve all invalid staging rows before approval.']);
        }
        if ($batch->status === 'approved') {
            throw ValidationException::withMessages(['batch' => 'This import batch is already approved.']);
        }

        return DB::connection('exam')->transaction(function () use ($batch, $actor, $note): int {
            $state = $this->state(true);
            $version = max(1, ((int) $state->current_version) + 1);
            $before = $this->snapshot((int) $state->current_version);

            foreach ($batch->rows()->where('validation_status', 'valid')->orderBy('row_number')->cursor() as $row) {
                $this->persistNormalized($row->normalized_data, $version, 'excel');
            }

            $state->update([
                'status' => CircularProcessingStatus::Approved,
                'current_version' => $version,
                'approved_version' => $version,
                'approved_by' => $actor->id,
                'approved_at' => now(),
                'is_stale' => false,
                'stale_reason' => null,
                'summary' => [
                    'entries' => $batch->valid_rows,
                    'posts' => (int) CircularEntry::query()->where('version', $version)->where('status', 'active')->sum('post_count'),
                    'source_batch_id' => $batch->id,
                ],
            ]);

            $batch->update([
                'status' => 'approved', 'approved_by' => $actor->id, 'approved_at' => now(),
                'approved_version' => $version, 'approval_note' => $note,
            ]);

            $after = $this->snapshot($version);
            $this->audit->record('circular_import_approved', $actor, $note, ['dataset'], $before, $after, [
                'batch_id' => $batch->id, 'version' => $version, 'rows' => $batch->valid_rows,
            ]);

            return $version;
        });
    }

    public function createManual(array $input, User $actor): CircularEntry
    {
        $result = $this->validator->validate($input);
        if (! $result['valid']) {
            throw ValidationException::withMessages(['entry' => $result['errors']]);
        }

        return DB::connection('exam')->transaction(function () use ($result, $actor): CircularEntry {
            $version = $this->ensureDraftVersion($actor, 'Manual Circular entry started.');
            $entry = $this->persistNormalized($result['data'], $version, 'ui');
            $this->audit->record('circular_entry_created', $actor, null, ['entry'], null, $entry->load('bachelorSubjects', 'prsSubjects')->toArray(), ['version' => $version]);
            return $entry;
        });
    }

    public function updateManual(CircularEntry $entry, array $input, User $actor, string $reason): CircularEntry
    {
        $result = $this->validator->validate($input);
        if (! $result['valid']) {
            throw ValidationException::withMessages(['entry' => $result['errors']]);
        }

        return DB::connection('exam')->transaction(function () use ($entry, $result, $actor, $reason): CircularEntry {
            $version = $this->ensureDraftVersion($actor, $reason);
            if ($entry->version !== $version) {
                $entry = $this->findEquivalentInVersion($entry, $version);
            }
            $before = $entry->load('bachelorSubjects', 'prsSubjects')->toArray();
            $data = $result['data'];
            $entry->update($this->entryPayload($data, $version, 'ui'));
            $entry->bachelorSubjects()->delete();
            $entry->prsSubjects()->delete();
            $this->syncEligibility($entry, $data);
            $after = $entry->fresh()->load('bachelorSubjects', 'prsSubjects')->toArray();
            $this->audit->record('circular_entry_updated', $actor, $reason, ['entry'], $before, $after, ['version' => $version]);
            return $entry->fresh();
        });
    }

    public function deleteManual(CircularEntry $entry, User $actor, string $reason): void
    {
        DB::connection('exam')->transaction(function () use ($entry, $actor, $reason): void {
            $version = $this->ensureDraftVersion($actor, $reason);
            if ($entry->version !== $version) {
                $entry = $this->findEquivalentInVersion($entry, $version);
            }
            $before = $entry->load('bachelorSubjects', 'prsSubjects')->toArray();
            $entry->delete();
            $this->audit->record('circular_entry_deleted', $actor, $reason, ['entry'], $before, null, ['version' => $version]);
        });
    }

    public function state(bool $lock = false): CircularProcessingState
    {
        $query = CircularProcessingState::query()->where('id', 1);
        if ($lock) {
            $query->lockForUpdate();
        }
        return $query->firstOrCreate(['id' => 1], ['status' => CircularProcessingStatus::NotStarted, 'current_version' => 0]);
    }

    public function ensureDraftVersion(User $actor, string $reason): int
    {
        $state = $this->state(true);
        if ($state->current_version === 0) {
            $state->update(['current_version' => 1, 'status' => CircularProcessingStatus::Draft]);
            return 1;
        }

        if ($state->status === CircularProcessingStatus::Draft) {
            return (int) $state->current_version;
        }

        $oldVersion = (int) $state->current_version;
        $newVersion = $oldVersion + 1;
        foreach (CircularEntry::query()->with(['bachelorSubjects', 'prsSubjects'])->where('version', $oldVersion)->orderBy('id')->cursor() as $entry) {
            $copy = $entry->replicate(['id', 'created_at', 'updated_at']);
            $copy->version = $newVersion;
            $copy->source = 'ui';
            $copy->save();
            foreach ($entry->bachelorSubjects as $subject) {
                $copy->bachelorSubjects()->create(['subject_code' => $subject->subject_code]);
            }
            foreach ($entry->prsSubjects as $prs) {
                $copy->prsSubjects()->create(['prs_code' => $prs->prs_code]);
            }
        }

        $state->update([
            'current_version' => $newVersion, 'status' => CircularProcessingStatus::Draft,
            'is_stale' => true, 'stale_reason' => 'Circular changed after an approved/confirmed dataset. '.$reason,
        ]);
        $this->audit->record('circular_version_forked_for_edit', $actor, $reason, ['version'], ['version' => $oldVersion], ['version' => $newVersion]);

        return $newVersion;
    }

    private function persistNormalized(array $data, int $version, string $source): CircularEntry
    {
        $entry = CircularEntry::query()->create($this->entryPayload($data, $version, $source));
        $this->syncEligibility($entry, $data);
        return $entry;
    }

    private function entryPayload(array $data, int $version, string $source): array
    {
        return [
            'cadre_serial' => $data['cadre_serial'], 'sub_serial' => $data['sub_serial'],
            'cadre_code' => $data['cadre_code'], 'sub_cadre_code' => $data['sub_cadre_code'],
            'effective_code' => $data['effective_code'], 'cadre_type' => $data['cadre_type'],
            'cadre_name_snapshot' => $data['cadre_name_snapshot'], 'cadre_name_bn_snapshot' => $data['cadre_name_bn_snapshot'],
            'post_name_snapshot' => $data['post_name_snapshot'], 'post_name_bn_snapshot' => $data['post_name_bn_snapshot'],
            'post_count' => $data['post_count'], 'status' => $data['status'], 'note' => $data['note'],
            'source' => $source, 'version' => $version,
        ];
    }

    private function syncEligibility(CircularEntry $entry, array $data): void
    {
        foreach ($data['bachelor_subject_codes'] as $code) {
            $entry->bachelorSubjects()->create(['subject_code' => (int) $code]);
        }
        foreach ($data['prs_codes'] as $code) {
            $entry->prsSubjects()->create(['prs_code' => (string) $code]);
        }
    }

    private function findEquivalentInVersion(CircularEntry $entry, int $version): CircularEntry
    {
        return CircularEntry::query()->where('version', $version)
            ->where('cadre_serial', $entry->cadre_serial)
            ->where('sub_serial', $entry->sub_serial)
            ->where('effective_code', $entry->effective_code)
            ->firstOrFail();
    }

    private function snapshot(int $version): array
    {
        if ($version <= 0) {
            return ['version' => 0, 'entries' => 0, 'posts' => 0];
        }
        return [
            'version' => $version,
            'entries' => CircularEntry::query()->where('version', $version)->count(),
            'posts' => (int) CircularEntry::query()->where('version', $version)->where('status', 'active')->sum('post_count'),
        ];
    }
}
