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
    private const DOWNSTREAM_STAGES = [
        'choice_validation' => 'Choice Validation',
        'merit_generation' => 'Merit Generation',
        'choice_optimization' => 'Choice Optimization',
        'allocation_preparation' => 'Allocation Preparation / Allocation',
    ];

    public function __construct(
        private readonly CircularEntryValidator $validator,
        private readonly CircularAuditService $audit,
        private readonly \App\Services\Dependencies\DownstreamStalePropagationService $downstream,
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

            $summary = [
                'entries' => $batch->valid_rows,
                'posts' => (int) CircularEntry::query()->where('version', $version)->where('status', 'active')->sum('post_count'),
                'source_batch_id' => $batch->id,
                'downstream_impact' => $this->freshDownstreamImpact(),
            ];

            $state->update([
                'status' => CircularProcessingStatus::Approved,
                'current_version' => $version,
                'approved_version' => $version,
                'approved_by' => $actor->id,
                'approved_at' => now(),
                'confirmed_version' => null,
                'confirmed_by' => null,
                'confirmed_at' => null,
                'confirmation_notes' => null,
                'finalized_version' => null,
                'finalized_by' => null,
                'finalized_at' => null,
                'is_stale' => false,
                'stale_reason' => null,
                'summary' => $summary,
            ]);

            $batch->update([
                'status' => 'approved',
                'approved_by' => $actor->id,
                'approved_at' => now(),
                'approved_version' => $version,
                'approval_note' => $note,
            ]);

            $after = $this->snapshot($version);
            $this->audit->record('circular_import_approved', $actor, $note, ['dataset'], $before, $after, [
                'batch_id' => $batch->id,
                'version' => $version,
                'rows' => $batch->valid_rows,
            ]);

            if (($before['version'] ?? 0) > 0) {
                $this->downstream->propagate(
                    'circular',
                    'Circular dataset advanced to version '.$version.'. Re-run dependent modules.',
                    (int) $actor->id,
                );
            }

            return $version;
        });
    }

    public function approveCurrentDraft(User $actor, string $note): int
    {
        return DB::connection('exam')->transaction(function () use ($actor, $note): int {
            $state = $this->state(true);

            if ($state->status !== CircularProcessingStatus::Draft || (int) $state->current_version < 1) {
                throw ValidationException::withMessages([
                    'approval_note' => 'Only the current Draft Circular can be approved as effective.',
                ]);
            }

            $version = (int) $state->current_version;
            $entries = CircularEntry::query()->where('version', $version)->count();
            if ($entries < 1) {
                throw ValidationException::withMessages([
                    'approval_note' => 'The current Draft Circular has no entries to approve.',
                ]);
            }

            $before = [
                'status' => $state->status->value,
                'current_version' => $version,
                'approved_version' => $state->approved_version,
                'is_stale' => (bool) $state->is_stale,
                'stale_reason' => $state->stale_reason,
            ];

            $summary = (array) $state->summary;
            // Keep downstream_impact unchanged. A corrected Circular can be effective
            // while already-produced downstream results remain stale until regenerated.
            $summary['entries'] = $entries;
            $summary['posts'] = (int) CircularEntry::query()
                ->where('version', $version)
                ->where('status', 'active')
                ->sum('post_count');
            $summary['last_draft_approval_note'] = $note;
            $summary['last_draft_approval_at'] = now()->toIso8601String();

            $state->update([
                'status' => CircularProcessingStatus::Approved,
                'approved_version' => $version,
                'approved_by' => $actor->id,
                'approved_at' => now(),
                'confirmed_version' => null,
                'confirmed_by' => null,
                'confirmed_at' => null,
                'confirmation_notes' => null,
                'finalized_version' => null,
                'finalized_by' => null,
                'finalized_at' => null,
                'is_stale' => false,
                'stale_reason' => null,
                'summary' => $summary,
            ]);

            $after = [
                'status' => CircularProcessingStatus::Approved->value,
                'current_version' => $version,
                'approved_version' => $version,
                'is_stale' => false,
                'stale_reason' => null,
            ];

            $this->audit->record(
                'circular_draft_approved_as_effective',
                $actor,
                $note,
                ['status', 'approved_version', 'is_stale'],
                $before,
                $after,
                ['version' => $version, 'downstream_impact' => $summary['downstream_impact'] ?? []]
            );

            return $version;
        });
    }

    public function createManual(array $input, User $actor, string $reason): CircularEntry
    {
        $result = $this->validator->validate($input);
        if (! $result['valid']) {
            throw ValidationException::withMessages(['entry' => $result['errors']]);
        }

        return DB::connection('exam')->transaction(function () use ($result, $actor, $reason): CircularEntry {
            $version = $this->ensureDraftVersion($actor, $reason);
            $entry = $this->persistNormalized($result['data'], $version, 'ui');
            $this->audit->record(
                'circular_entry_created',
                $actor,
                $reason,
                ['entry'],
                null,
                $entry->load('bachelorSubjects', 'prsSubjects')->toArray(),
                ['version' => $version]
            );

            return $entry;
        });
    }

    /**
     * @return array{entry:CircularEntry,changed:bool,version:int}
     */
    public function updateManual(CircularEntry $entry, array $input, User $actor, string $reason): array
    {
        $result = $this->validator->validate($input);
        if (! $result['valid']) {
            throw ValidationException::withMessages(['entry' => $result['errors']]);
        }

        return DB::connection('exam')->transaction(function () use ($entry, $result, $actor, $reason): array {
            $entry->loadMissing('bachelorSubjects', 'prsSubjects');

            // A no-op must not fork a version and must not create a false audit event.
            if (! $this->hasMeaningfulChange($entry, $result['data'])) {
                return [
                    'entry' => $entry,
                    'changed' => false,
                    'version' => (int) $entry->version,
                ];
            }

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

            $this->audit->record(
                'circular_entry_updated',
                $actor,
                $reason,
                $this->changedFields($before, $after),
                $before,
                $after,
                ['version' => $version]
            );

            return [
                'entry' => $entry->fresh(),
                'changed' => true,
                'version' => $version,
            ];
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

            $this->audit->record(
                'circular_entry_deleted',
                $actor,
                $reason,
                ['entry'],
                $before,
                null,
                ['version' => $version]
            );
        });
    }

    public function state(bool $lock = false): CircularProcessingState
    {
        $query = CircularProcessingState::query()->where('id', 1);
        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->firstOrCreate(
            ['id' => 1],
            ['status' => CircularProcessingStatus::NotStarted, 'current_version' => 0]
        );
    }

    public function ensureDraftVersion(User $actor, string $reason): int
    {
        $state = $this->state(true);

        if ((int) $state->current_version === 0) {
            $state->update([
                'current_version' => 1,
                'status' => CircularProcessingStatus::Draft,
                'confirmed_version' => null,
                'confirmed_by' => null,
                'confirmed_at' => null,
                'confirmation_notes' => null,
                'finalized_version' => null,
                'finalized_by' => null,
                'finalized_at' => null,
                'summary' => array_merge((array) $state->summary, [
                    'downstream_impact' => $this->freshDownstreamImpact(),
                ]),
            ]);

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

        $staleReason = 'Circular changed after an approved/confirmed dataset. '.$reason;
        $summary = (array) $state->summary;
        $summary['downstream_impact'] = $this->staleDownstreamImpact($staleReason, $newVersion);
        $summary['last_manual_change_reason'] = $reason;
        $summary['last_manual_change_at'] = now()->toIso8601String();

        $state->update([
            'current_version' => $newVersion,
            'status' => CircularProcessingStatus::Draft,
            'confirmed_version' => null,
            'confirmed_by' => null,
            'confirmed_at' => null,
            'confirmation_notes' => null,
            'finalized_version' => null,
            'finalized_by' => null,
            'finalized_at' => null,
            'is_stale' => true,
            'stale_reason' => $staleReason,
            'summary' => $summary,
        ]);

        $this->audit->record(
            'circular_version_forked_for_edit',
            $actor,
            $reason,
            ['version', 'downstream_impact'],
            ['version' => $oldVersion],
            ['version' => $newVersion],
            ['downstream_impact' => $summary['downstream_impact']]
        );

        $this->downstream->propagate(
            'circular',
            $staleReason,
            (int) $actor->id,
        );

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
            'cadre_serial' => $data['cadre_serial'],
            'sub_serial' => $data['sub_serial'],
            'cadre_code' => $data['cadre_code'],
            'sub_cadre_code' => $data['sub_cadre_code'],
            'effective_code' => $data['effective_code'],
            'cadre_type' => $data['cadre_type'],
            'cadre_name_snapshot' => $data['cadre_name_snapshot'],
            'cadre_name_bn_snapshot' => $data['cadre_name_bn_snapshot'],
            'post_name_snapshot' => $data['post_name_snapshot'],
            'post_name_bn_snapshot' => $data['post_name_bn_snapshot'],
            'post_count' => $data['post_count'],
            'status' => $data['status'],
            'note' => $data['note'],
            'source' => $source,
            'version' => $version,
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
        return CircularEntry::query()
            ->where('version', $version)
            ->where('cadre_serial', $entry->cadre_serial)
            ->where('sub_serial', $entry->sub_serial)
            ->where('effective_code', $entry->effective_code)
            ->firstOrFail();
    }

    private function hasMeaningfulChange(CircularEntry $entry, array $data): bool
    {
        return $this->comparableEntry($entry) !== $this->comparableData($data);
    }

    private function comparableEntry(CircularEntry $entry): array
    {
        return [
            'cadre_serial' => (int) $entry->cadre_serial,
            'sub_serial' => $entry->sub_serial === null ? null : (int) $entry->sub_serial,
            'cadre_code' => (int) $entry->cadre_code,
            'sub_cadre_code' => $entry->sub_cadre_code === null ? null : (int) $entry->sub_cadre_code,
            'effective_code' => (int) $entry->effective_code,
            'cadre_type' => $entry->cadre_type->value,
            'cadre_name_snapshot' => $entry->cadre_name_snapshot,
            'cadre_name_bn_snapshot' => $entry->cadre_name_bn_snapshot,
            'post_name_snapshot' => $entry->post_name_snapshot,
            'post_name_bn_snapshot' => $entry->post_name_bn_snapshot,
            'post_count' => (int) $entry->post_count,
            'status' => strtolower((string) $entry->status),
            'note' => $entry->note,
            'bachelor_subject_codes' => $entry->bachelorSubjects->pluck('subject_code')->map(fn ($value) => (string) $value)->sort()->values()->all(),
            'prs_codes' => $entry->prsSubjects->pluck('prs_code')->map(fn ($value) => (string) $value)->sort()->values()->all(),
        ];
    }

    private function comparableData(array $data): array
    {
        $bachelors = collect($data['bachelor_subject_codes'])->map(fn ($value) => (string) $value)->sort()->values()->all();
        $prs = collect($data['prs_codes'])->map(fn ($value) => (string) $value)->sort()->values()->all();

        return [
            'cadre_serial' => (int) $data['cadre_serial'],
            'sub_serial' => $data['sub_serial'] === null ? null : (int) $data['sub_serial'],
            'cadre_code' => (int) $data['cadre_code'],
            'sub_cadre_code' => $data['sub_cadre_code'] === null ? null : (int) $data['sub_cadre_code'],
            'effective_code' => (int) $data['effective_code'],
            'cadre_type' => is_object($data['cadre_type']) ? $data['cadre_type']->value : (string) $data['cadre_type'],
            'cadre_name_snapshot' => $data['cadre_name_snapshot'],
            'cadre_name_bn_snapshot' => $data['cadre_name_bn_snapshot'],
            'post_name_snapshot' => $data['post_name_snapshot'],
            'post_name_bn_snapshot' => $data['post_name_bn_snapshot'],
            'post_count' => (int) $data['post_count'],
            'status' => strtolower((string) $data['status']),
            'note' => $data['note'],
            'bachelor_subject_codes' => $bachelors,
            'prs_codes' => $prs,
        ];
    }

    private function changedFields(array $before, array $after): array
    {
        $fields = [
            'cadre_serial', 'sub_serial', 'cadre_code', 'sub_cadre_code', 'effective_code', 'cadre_type',
            'cadre_name_snapshot', 'cadre_name_bn_snapshot', 'post_name_snapshot', 'post_name_bn_snapshot',
            'post_count', 'status', 'note',
        ];

        $changed = [];
        foreach ($fields as $field) {
            $beforeValue = data_get($before, $field);
            $afterValue = data_get($after, $field);
            if ($beforeValue != $afterValue) {
                $changed[] = $field;
            }
        }

        $beforeBachelor = collect($before['bachelor_subjects'] ?? [])->pluck('subject_code')->map(fn ($v) => (string) $v)->sort()->values()->all();
        $afterBachelor = collect($after['bachelor_subjects'] ?? [])->pluck('subject_code')->map(fn ($v) => (string) $v)->sort()->values()->all();
        if ($beforeBachelor !== $afterBachelor) {
            $changed[] = 'bachelor_subject_codes';
        }

        $beforePrs = collect($before['prs_subjects'] ?? [])->pluck('prs_code')->map(fn ($v) => (string) $v)->sort()->values()->all();
        $afterPrs = collect($after['prs_subjects'] ?? [])->pluck('prs_code')->map(fn ($v) => (string) $v)->sort()->values()->all();
        if ($beforePrs !== $afterPrs) {
            $changed[] = 'prs_codes';
        }

        return $changed;
    }

    private function freshDownstreamImpact(): array
    {
        return collect(self::DOWNSTREAM_STAGES)->mapWithKeys(fn ($label, $key) => [
            $key => [
                'label' => $label,
                'status' => 'not_started',
                'reason' => null,
            ],
        ])->all();
    }

    private function staleDownstreamImpact(string $reason, int $circularVersion): array
    {
        return collect(self::DOWNSTREAM_STAGES)->mapWithKeys(fn ($label, $key) => [
            $key => [
                'label' => $label,
                'status' => 'stale',
                'reason' => $reason,
                'circular_version' => $circularVersion,
                'marked_at' => now()->toIso8601String(),
            ],
        ])->all();
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
