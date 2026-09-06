<?php

namespace App\Services\Allocation;

use App\Models\AllocationInputCandidate;
use App\Models\AllocationInputFreeze;
use App\Models\AllocationInputQueueEntry;
use App\Models\AllocationProcessingAudit;
use App\Models\AllocationProcessingState;
use App\Models\ChoiceOptimizationHistoricalChoice;
use App\Models\ChoiceOptimizationProcessingState;
use App\Models\ChoiceOptimizationSetting;
use App\Models\MeritCadreRank;
use App\Models\MeritResult;
use App\Models\Registration;
use App\Models\PreliminaryProcessingState;
use App\Models\WrittenProcessingState;
use App\Models\VivaProcessingState;
use App\Enums\PreliminaryProcessingStatus;
use App\Enums\WrittenProcessingStatus;
use App\Enums\VivaProcessingStatus;
use App\Services\ChoiceOptimization\ChoiceOptimizationHistoricalChoiceService;
use App\Services\ChoiceValidation\ChoiceValidationFinalizedDatasetService;
use App\Services\Circular\CircularFinalizedDatasetService;
use App\Services\Merit\MeritFinalizedDatasetService;
use App\Services\Tabulation\TabulationFinalizedDatasetService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class AllocationInputFreezeService
{
    public function __construct(
        private readonly CircularFinalizedDatasetService $circular,
        private readonly ChoiceValidationFinalizedDatasetService $choiceValidation,
        private readonly MeritFinalizedDatasetService $merit,
        private readonly TabulationFinalizedDatasetService $tabulation,
        private readonly ChoiceOptimizationHistoricalChoiceService $optimizedChoices,
        private readonly AllocationSettingsService $settings,
        private readonly AllocationSeatBreakupService $seatBreakup,
        private readonly AllocationRunStaleService $runStale,
    ) {}

    /**
     * Strictly verify all direct Allocation inputs, materialize immutable candidate
     * input rows, build deterministic cadre queues, and persist a fingerprint.
     */
    public function freeze(?int $actorId, ?callable $progress = null): AllocationInputFreeze
    {
        $this->progress($progress, 'VERIFYING_INPUTS', 5, 'Strictly verifying authoritative inputs…');
        $before = $this->strictSourceSnapshot();
        $this->progress($progress, 'BUILDING_SNAPSHOT', 15, 'Building immutable candidate snapshot and deterministic queues…');
        $built = $this->buildRows($before, $progress);

        // Re-verify after the potentially long read/build pass. If any direct
        // authoritative source changed while we were building, do not freeze.
        $this->progress($progress, 'REVERIFYING_INPUTS', 78, 'Re-verifying direct inputs before commit…');
        $after = $this->strictSourceSnapshot();
        $this->assertSameDirectSources($before, $after);

        if (! hash_equals($built['registration_hash'], $this->registrationHashForMeritRun((int) $after['merit']['processing_run_id']))) {
            throw ValidationException::withMessages([
                'allocation_input' => 'REGISTRATION_INPUT_CHANGED_DURING_FREEZE: Registration identity/category/quota data changed while Allocation input was being built. Retry after upstream data is stable.',
            ]);
        }

        $sourceSnapshot = $after;
        $sourceSnapshot['registration'] = [
            'dataset_hash' => $built['registration_hash'],
            'row_count' => count($built['candidates']),
        ];
        $sourceSnapshot['allocation_ready_choice'] = [
            'source' => $built['choice_source'],
            'dataset_hash' => $after['choice']['dataset_hash'],
            'row_count' => $built['choice_row_count'],
        ];

        $this->progress($progress, 'HASHING', 84, 'Calculating immutable input fingerprint and deterministic queue hash…');
        $fingerprint = $this->fingerprint($sourceSnapshot);
        $queueHash = $this->hashQueueRows($built['queues']);
        $this->progress($progress, 'PERSISTING', 90, 'Persisting frozen snapshot and queue rows…');

        $hadPriorFreeze = AllocationInputFreeze::query()->exists();

        $freeze = DB::connection('exam')->transaction(function () use ($actorId, $built, $sourceSnapshot, $fingerprint, $queueHash): AllocationInputFreeze {
            $state = AllocationProcessingState::query()->whereKey(1)->lockForUpdate()->firstOrFail();
            $fromStatus = (string) $state->status;

            if (in_array($fromStatus, ['running', 'finalizing', 'finalized', 'phase1_queued', 'phase1_running'], true)) {
                throw ValidationException::withMessages([
                    'allocation_input' => 'Allocation input cannot be re-frozen while an Allocation run is running/finalizing/finalized. Use the explicit rerun/revision workflow.',
                ]);
            }

            AllocationInputFreeze::query()
                ->whereIn('status', ['frozen', 'stale'])
                ->update(['status' => 'superseded', 'updated_at' => now()]);
            $nextVersion = ((int) AllocationInputFreeze::query()->max('version')) + 1;
            $now = now();

            $freeze = AllocationInputFreeze::query()->create([
                'version' => $nextVersion,
                'status' => 'frozen',
                'choice_source' => $built['choice_source'],
                'source_snapshot' => $sourceSnapshot,
                'registration_hash' => $built['registration_hash'],
                'circular_hash' => $sourceSnapshot['circular']['dataset_hash'],
                'choice_hash' => $sourceSnapshot['choice']['dataset_hash'],
                'merit_hash' => $sourceSnapshot['merit']['dataset_hash'],
                'settings_hash' => $sourceSnapshot['allocation_settings']['dataset_hash'],
                'seat_breakup_hash' => $sourceSnapshot['seat_breakup']['dataset_hash'],
                'input_fingerprint' => $fingerprint,
                'queue_hash' => $queueHash,
                'total_candidates' => count($built['candidates']),
                'choice_ready_candidates' => $built['choice_ready_candidates'],
                'total_queue_entries' => count($built['queues']),
                'skipped_choice_entries' => $built['skipped_choice_entries'],
                'frozen_by' => $actorId,
                'frozen_at' => $now,
            ]);

            foreach (array_chunk($built['candidates'], 1000) as $chunk) {
                $rows = array_map(fn (array $row): array => $row + [
                    'input_freeze_id' => $freeze->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $chunk);
                AllocationInputCandidate::query()->insert($rows);
            }

            foreach (array_chunk($built['queues'], 1000) as $chunk) {
                $rows = array_map(fn (array $row): array => $row + [
                    'input_freeze_id' => $freeze->id,
                    'created_at' => $now,
                ], $chunk);
                AllocationInputQueueEntry::query()->insert($rows);
            }

            $state->forceFill([
                'status' => 'input_frozen',
                'is_stale' => false,
                'stale_reason' => null,
                'source_snapshot' => [
                    'input_freeze_id' => (int) $freeze->id,
                    'input_freeze_version' => (int) $freeze->version,
                    'queue_hash' => $queueHash,
                    'sources' => $sourceSnapshot,
                ],
                'input_fingerprint' => $fingerprint,
                'output_hash' => null,
                'finalized_by' => null,
                'finalized_at' => null,
                'phase' => 'COMPLETED',
                'progress_percent' => 100,
                'progress_current' => count($built['queues']),
                'progress_total' => count($built['queues']),
                'progress_message' => 'Frozen Allocation input and deterministic queues completed.',
                'last_error' => null,
            ])->save();

            AllocationProcessingAudit::query()->create([
                'event' => 'ALLOCATION_INPUT_FROZEN',
                'actor_id' => $actorId,
                'from_status' => $fromStatus,
                'to_status' => 'input_frozen',
                'context' => [
                    'input_freeze_id' => (int) $freeze->id,
                    'input_freeze_version' => (int) $freeze->version,
                    'input_fingerprint' => $fingerprint,
                    'queue_hash' => $queueHash,
                    'choice_source' => $built['choice_source'],
                    'total_candidates' => count($built['candidates']),
                    'choice_ready_candidates' => $built['choice_ready_candidates'],
                    'total_queue_entries' => count($built['queues']),
                    'skipped_choice_entries' => $built['skipped_choice_entries'],
                ],
                'created_at' => $now,
            ]);

            return $freeze->refresh();
        });

        // A2 is a versioned immutable input authority. A successful re-freeze
        // requires fresh Phase-1/Phase-2 processing even when the data hashes
        // happen to be identical, so operators can prove exact-version lineage.
        if ($hadPriorFreeze) {
            $this->runStale->staleA3AndA4(
                "A2 Allocation Input Freeze was re-built as v{$freeze->version}. Re-run A3 Phase-1 and A4 Phase-2.",
                $actorId
            );
        }

        return $freeze;
    }

    /** Lightweight landing-page verification: no large dataset re-hash. */
    public function storedCurrentSummary(): array
    {
        $state = AllocationProcessingState::query()->firstOrCreate(['id' => 1], ['status' => 'not_started']);
        $freezeId = (int) data_get($state->source_snapshot, 'input_freeze_id', 0);
        $freeze = $freezeId > 0 ? AllocationInputFreeze::query()->find($freezeId) : null;

        if (! $freeze || (string) $freeze->status !== 'frozen' || ! $freeze->input_fingerprint || ! $freeze->queue_hash) {
            throw new RuntimeException('No current frozen Allocation input snapshot exists. Freeze direct inputs before Allocation.');
        }
        if (! hash_equals((string) $state->input_fingerprint, (string) $freeze->input_fingerprint)) {
            throw new RuntimeException('ALLOCATION_INPUT_STATE_FINGERPRINT_MISMATCH. Re-freeze Allocation inputs.');
        }

        // Compare current stored metadata only. Strict full re-hash runs before A3 processing.
        $circular = $this->circular->storedFinalizedSummary();
        $choice = $this->choiceValidation->storedFinalizedSummary();
        $tabulation = $this->tabulation->storedFinalizedSummary();
        $merit = $this->storedMeritSummary();
        $setting = $this->settings->storedFinalizedSummary();
        $seat = $this->storedSeatSummary();
        $co = ChoiceOptimizationSetting::query()->first();

        $optimizationEnabled = (bool) ($co?->optimization_enabled);
        $expectedChoiceSource = $optimizationEnabled ? 'choice_optimization' : 'choice_validation';
        $expectedChoiceHash = $optimizationEnabled
            ? (string) (ChoiceOptimizationProcessingState::query()->first()?->dataset_hash ?? '')
            : (string) $choice['dataset_hash'];

        if ((string) $freeze->choice_source !== $expectedChoiceSource) {
            throw new RuntimeException('Allocation-ready Choice source changed after input freeze. Re-freeze direct inputs.');
        }
        if ((int) data_get($freeze->source_snapshot, 'seat_breakup.version', 0) !== (int) $seat['version']) {
            throw new RuntimeException('Seat Breakup version changed after Allocation input freeze. Re-freeze direct inputs.');
        }

        if ((string) data_get($freeze->source_snapshot, 'tabulation.dataset_hash', '') !== (string) $tabulation['dataset_hash']) {
            throw new RuntimeException('Tabulation changed after Allocation input freeze. Re-freeze direct inputs.');
        }

        $pairs = [
            [(string) $freeze->circular_hash, (string) $circular['dataset_hash'], 'Circular'],
            [(string) $freeze->choice_hash, $expectedChoiceHash, 'Allocation-ready Choice'],
            [(string) $freeze->merit_hash, (string) $merit['dataset_hash'], 'Merit'],
            [(string) $freeze->settings_hash, (string) $setting['dataset_hash'], 'Allocation Settings'],
            [(string) $freeze->seat_breakup_hash, (string) $seat['dataset_hash'], 'Seat Breakup'],
        ];
        foreach ($pairs as [$frozen, $current, $label]) {
            if ($current === '' || ! hash_equals($frozen, $current)) {
                throw new RuntimeException("{$label} changed after Allocation input freeze. Re-freeze direct inputs.");
            }
        }

        return [
            'version' => (int) $freeze->version,
            'dataset_hash' => (string) $freeze->input_fingerprint,
            'queue_hash' => (string) $freeze->queue_hash,
            'choice_source' => (string) $freeze->choice_source,
            'total_candidates' => (int) $freeze->total_candidates,
            'choice_ready_candidates' => (int) $freeze->choice_ready_candidates,
            'total_queue_entries' => (int) $freeze->total_queue_entries,
            'skipped_choice_entries' => (int) $freeze->skipped_choice_entries,
            'frozen_at' => $freeze->frozen_at,
        ];
    }

    /** Strict pre-run verification for A3 and later. */
    public function verifiedCurrent(): AllocationInputFreeze
    {
        $summary = $this->storedCurrentSummary();
        $freeze = AllocationInputFreeze::query()->where('version', $summary['version'])->firstOrFail();
        $current = $this->strictSourceSnapshot();
        $registrationHash = $this->registrationHashForMeritRun((int) $current['merit']['processing_run_id']);

        if (! hash_equals((string) $freeze->registration_hash, $registrationHash)) {
            throw ValidationException::withMessages(['allocation_input' => 'REGISTRATION_INPUT_HASH_MISMATCH: Registration identity/category/quota data changed after Allocation input freeze.']);
        }
        if (! hash_equals((string) $freeze->circular_hash, (string) $current['circular']['dataset_hash'])
            || ! hash_equals((string) $freeze->choice_hash, (string) $current['choice']['dataset_hash'])
            || ! hash_equals((string) $freeze->merit_hash, (string) $current['merit']['dataset_hash'])
            || ! hash_equals((string) $freeze->settings_hash, (string) $current['allocation_settings']['dataset_hash'])
            || ! hash_equals((string) $freeze->seat_breakup_hash, (string) $current['seat_breakup']['dataset_hash'])) {
            throw ValidationException::withMessages(['allocation_input' => 'ALLOCATION_INPUT_SOURCE_HASH_MISMATCH: A direct authoritative source changed after freeze. Re-freeze before Allocation.']);
        }

        $snapshot = $current;
        $snapshot['registration'] = ['dataset_hash' => $registrationHash, 'row_count' => (int) $freeze->total_candidates];
        $snapshot['allocation_ready_choice'] = [
            'source' => (string) $freeze->choice_source,
            'dataset_hash' => (string) $current['choice']['dataset_hash'],
        ];
        if (! hash_equals((string) $freeze->input_fingerprint, $this->fingerprint($snapshot))) {
            throw ValidationException::withMessages(['allocation_input' => 'ALLOCATION_INPUT_FINGERPRINT_MISMATCH: Frozen input fingerprint no longer matches current verified direct inputs.']);
        }

        $queueHash = $this->hashQueueRowsFromDatabase((int) $freeze->id);
        if (! hash_equals((string) $freeze->queue_hash, $queueHash)) {
            throw ValidationException::withMessages(['allocation_input' => 'ALLOCATION_QUEUE_HASH_MISMATCH: Frozen deterministic queue rows were modified. Re-freeze before Allocation.']);
        }

        return $freeze;
    }

    /** @return array<string,mixed> */
    private function strictSourceSnapshot(): array
    {
        $this->assertRequiredUpstreamStatuses();
        $circular = $this->circular->verifiedSummary();
        $validated = $this->choiceValidation->verifiedSummary();
        $tabulation = $this->tabulation->verifiedSummary();
        $merit = $this->merit->verifiedSummary();
        $setting = $this->settings->verified();
        $seat = $this->seatBreakup->verifiedFinalized();

        $optimization = ChoiceOptimizationSetting::query()->first();
        $optimizationEnabled = (bool) ($optimization?->optimization_enabled);

        if ($optimizationEnabled) {
            $state = ChoiceOptimizationProcessingState::query()->first();
            if (! $state || (string) $state->status !== 'finalized' || (bool) $state->is_stale || ! $state->dataset_hash) {
                throw ValidationException::withMessages(['choice_optimization' => 'Choice Optimization is enabled but is not current/finalized.']);
            }
            $actual = $this->optimizedChoices->outputHashFromDatabase();
            if (! hash_equals((string) $state->dataset_hash, $actual)) {
                throw ValidationException::withMessages(['choice_optimization' => 'CHOICE_OPTIMIZATION_HASH_MISMATCH. Reprocess/finalize Choice Optimization.']);
            }
            $choiceSource = 'choice_optimization';
            $choiceHash = $actual;
        } else {
            $choiceSource = 'choice_validation';
            $choiceHash = (string) $validated['dataset_hash'];
        }

        return [
            'circular' => [
                'version' => (int) $circular['version'],
                'dataset_hash' => (string) $circular['dataset_hash'],
            ],
            'choice_validation' => [
                'validation_version' => (int) $validated['validation_version'],
                'dataset_hash' => (string) $validated['dataset_hash'],
            ],
            'choice' => [
                'source' => $choiceSource,
                'optimization_enabled' => $optimizationEnabled,
                'dataset_hash' => $choiceHash,
            ],
            'tabulation' => [
                'processing_run_id' => (int) $tabulation['processing_run_id'],
                'processing_version' => (int) $tabulation['processing_version'],
                'dataset_hash' => (string) $tabulation['dataset_hash'],
            ],
            'merit' => [
                'processing_run_id' => (int) $merit['processing_run_id'],
                'processing_version' => (int) $merit['processing_version'],
                'dataset_hash' => (string) $merit['dataset_hash'],
            ],
            'allocation_settings' => [
                'dataset_hash' => (string) $setting->settings_hash,
            ],
            'seat_breakup' => [
                'version' => (int) $seat->version,
                'dataset_hash' => (string) $seat->dataset_hash,
                'circular_version' => (int) $seat->circular_version,
                'circular_hash' => (string) $seat->circular_hash,
            ],
        ];
    }

    /** @return array<string,mixed> */
    private function buildRows(array $sources, ?callable $progress = null): array
    {
        $runId = (int) $sources['merit']['processing_run_id'];
        $meritRows = MeritResult::query()
            ->where('processing_run_id', $runId)
            ->orderBy('registration_id')
            ->get();

        $registrationIds = $meritRows->pluck('registration_id')->map(fn ($v) => (int) $v)->all();
        $registrations = Registration::query()->whereIn('id', $registrationIds)->get()->keyBy('id');
        if ($registrations->count() !== count($registrationIds)) {
            throw ValidationException::withMessages(['registration' => 'One or more finalized Merit candidates cannot be resolved in Registration. Allocation input freeze is blocked.']);
        }

        $choices = $this->allocationReadyChoiceMap((string) $sources['choice']['source']);
        $seatVersion = $this->seatBreakup->verifiedFinalized();
        $seatRows = $seatVersion->rows()->with('circularEntry')->get()->keyBy('cadre_code');

        $technicalRanks = MeritCadreRank::query()
            ->where('processing_run_id', $runId)
            ->orderBy('registration_id')
            ->orderBy('cadre_code')
            ->get()
            ->groupBy('registration_id')
            ->map(fn ($rows) => $rows->keyBy('cadre_code'));

        $candidateRows = [];
        $queueRows = [];
        $choiceReady = 0;
        $skippedChoices = 0;

        $totalMeritRows = $meritRows->count();
        $processedMeritRows = 0;
        foreach ($meritRows as $merit) {
            $registration = $registrations->get((int) $merit->registration_id);
            $codes = array_values(array_unique(array_map('intval', $choices[(int) $merit->registration_id] ?? [])));
            $category = $merit->cadre_category;
            if ($category instanceof \BackedEnum) {
                $category = match ((int) $category->value) { 1 => 'GG', 2 => 'TT', 3 => 'GT', default => null };
            } else {
                $category = match ((int) $category) { 1 => 'GG', 2 => 'TT', 3 => 'GT', default => null };
            }
            $quota = [
                'CFF' => (bool) $registration->has_ff_quota,
                'EM' => (bool) $registration->has_em_quota,
                'PHC' => (bool) $registration->has_phc_quota,
            ];

            if ($codes !== []) {
                $choiceReady++;
            }

            $candidateRows[] = [
                'registration_id' => (int) $merit->registration_id,
                'merit_result_id' => (int) $merit->id,
                'user_id' => (string) $merit->user_id,
                'reg' => (string) $merit->reg,
                'cadre_category' => $category,
                'general_merit_position' => $merit->general_merit_position ? (int) $merit->general_merit_position : null,
                'quota_entitlement' => json_encode($quota, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                'choice_codes' => json_encode(array_map('strval', $codes), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                'choice_source' => (string) $sources['choice']['source'],
                'skip_reason' => $codes === [] ? 'NO_ALLOCATION_READY_CHOICE' : null,
            ];

            foreach ($codes as $index => $code) {
                $seat = $seatRows->get($code);
                if (! $seat || ! $seat->circularEntry || (int) $seat->total_post <= 0) {
                    $skippedChoices++;
                    continue;
                }

                $typeValue = $seat->circularEntry->cadre_type;
                $type = $typeValue instanceof \BackedEnum ? (string) $typeValue->value : (string) $typeValue;
                $generalPosition = $merit->general_merit_position ? (int) $merit->general_merit_position : null;
                $technicalPosition = null;
                $meritPosition = null;
                $meritSource = null;

                if ($type === 'GG') {
                    if ($generalPosition === null || ! (bool) $merit->general_merit_eligible) {
                        $skippedChoices++;
                        continue;
                    }
                    $meritPosition = $generalPosition;
                    $meritSource = 'GENERAL_MERIT';
                } elseif ($type === 'TT') {
                    $rank = $technicalRanks->get((int) $merit->registration_id)?->get($code);
                    if (! $rank) {
                        $skippedChoices++;
                        continue;
                    }
                    $technicalPosition = (int) $rank->cadre_merit_position;
                    if ($technicalPosition <= 0) {
                        $skippedChoices++;
                        continue;
                    }
                    $meritPosition = $technicalPosition;
                    $meritSource = 'TECHNICAL_CADRE_MERIT';
                } else {
                    $skippedChoices++;
                    continue;
                }

                $queueRows[] = [
                    'registration_id' => (int) $merit->registration_id,
                    'circular_entry_id' => (int) $seat->circular_entry_id,
                    'cadre_code' => $code,
                    'cadre_type' => $type,
                    'choice_position' => $index + 1,
                    'merit_position' => $meritPosition,
                    'merit_source' => $meritSource,
                    'general_merit_position' => $generalPosition,
                    'technical_merit_position' => $technicalPosition,
                    'eligible_cff' => $quota['CFF'],
                    'eligible_em' => $quota['EM'],
                    'eligible_phc' => $quota['PHC'],
                    'total_post' => (int) $seat->total_post,
                    'mq' => (int) $seat->mq,
                    'cff' => (int) $seat->cff,
                    'em' => (int) $seat->em,
                    'phc' => (int) $seat->phc,
                    'queue_key' => sprintf('%s:%06d', $type, $code),
                ];
            }

            $processedMeritRows++;
            if ($processedMeritRows === 1 || $processedMeritRows === $totalMeritRows || $processedMeritRows % 250 === 0) {
                $percent = 15 + (int) floor(($processedMeritRows / max(1, $totalMeritRows)) * 58);
                $this->progress($progress, 'BUILDING_QUEUES', $percent, 'Building candidate snapshot and cadre queues…', $processedMeritRows, $totalMeritRows);
            }
        }

        usort($queueRows, static function (array $a, array $b): int {
            return [$a['cadre_code'], $a['merit_position'], $a['choice_position'], $a['registration_id']]
                <=> [$b['cadre_code'], $b['merit_position'], $b['choice_position'], $b['registration_id']];
        });

        return [
            'choice_source' => (string) $sources['choice']['source'],
            'choice_row_count' => count($choices),
            'registration_hash' => $this->hashRegistrationRows($meritRows, $registrations),
            'candidates' => $candidateRows,
            'queues' => $queueRows,
            'choice_ready_candidates' => $choiceReady,
            'skipped_choice_entries' => $skippedChoices,
        ];
    }

    private function progress(?callable $progress, string $phase, int $percent, string $message, int $current = 0, int $total = 0): void
    {
        if ($progress !== null) {
            $progress($phase, $percent, $message, $current, $total);
        }
    }

    /** @return array<int,array<int,string>> */
    private function allocationReadyChoiceMap(string $source): array
    {
        if ($source === 'choice_optimization') {
            return ChoiceOptimizationHistoricalChoice::query()
                ->orderBy('registration_id')
                ->get(['registration_id', 'final_choice_codes'])
                ->mapWithKeys(fn ($row): array => [
                    (int) $row->registration_id => array_values(array_map('strval', (array) $row->final_choice_codes)),
                ])->all();
        }

        return $this->choiceValidation->choiceReadyResults()
            ->mapWithKeys(fn ($row): array => [
                (int) $row->registration_id => array_values(array_map('strval', (array) $row->validated_choice_codes)),
            ])->all();
    }

    private function registrationHashForMeritRun(int $runId): string
    {
        $meritRows = MeritResult::query()->where('processing_run_id', $runId)->orderBy('registration_id')->get(['id','registration_id','user_id','reg']);
        $ids = $meritRows->pluck('registration_id')->map(fn ($v) => (int) $v)->all();
        $registrations = Registration::query()->whereIn('id', $ids)->get()->keyBy('id');
        if ($registrations->count() !== count($ids)) {
            throw new RuntimeException('Registration row missing for a finalized Merit candidate.');
        }
        return $this->hashRegistrationRows($meritRows, $registrations);
    }

    private function hashRegistrationRows($meritRows, $registrations): string
    {
        $context = hash_init('sha256');
        foreach ($meritRows as $merit) {
            $r = $registrations->get((int) $merit->registration_id);
            hash_update($context, json_encode([
                'registration_id' => (int) $r->id,
                'user_id' => (string) $r->user_id,
                'reg' => (string) $r->reg,
                'cadre_category' => $r->cadre_category instanceof \BackedEnum ? $r->cadre_category->value : $r->cadre_category,
                'has_ff_quota' => (bool) $r->has_ff_quota,
                'has_em_quota' => (bool) $r->has_em_quota,
                'has_phc_quota' => (bool) $r->has_phc_quota,
                'bachelor_subject_code' => (string) ($r->bachelor_subject_code ?? ''),
                'post_related_subject_code' => (string) ($r->post_related_subject_code ?? ''),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
        }
        return hash_final($context);
    }

    private function hashQueueRows(array $rows): string
    {
        $context = hash_init('sha256');
        foreach ($rows as $row) {
            hash_update($context, json_encode($this->canonicalQueueRow($row), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
        }
        return hash_final($context);
    }

    private function hashQueueRowsFromDatabase(int $freezeId): string
    {
        $context = hash_init('sha256');
        AllocationInputQueueEntry::query()
            ->where('input_freeze_id', $freezeId)
            ->orderBy('cadre_code')->orderBy('merit_position')->orderBy('choice_position')->orderBy('registration_id')
            ->get()
            ->each(function ($row) use ($context): void {
                hash_update($context, json_encode($this->canonicalQueueRow($row->toArray()), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
            });
        return hash_final($context);
    }

    private function canonicalQueueRow(array $row): array
    {
        return [
            'registration_id' => (int) $row['registration_id'],
            'circular_entry_id' => (int) $row['circular_entry_id'],
            'cadre_code' => (int) $row['cadre_code'],
            'cadre_type' => (string) $row['cadre_type'],
            'choice_position' => (int) $row['choice_position'],
            'merit_position' => (int) $row['merit_position'],
            'merit_source' => (string) $row['merit_source'],
            'general_merit_position' => isset($row['general_merit_position']) ? (int) $row['general_merit_position'] : null,
            'technical_merit_position' => isset($row['technical_merit_position']) ? (int) $row['technical_merit_position'] : null,
            'eligible_cff' => (bool) $row['eligible_cff'],
            'eligible_em' => (bool) $row['eligible_em'],
            'eligible_phc' => (bool) $row['eligible_phc'],
            'total_post' => (int) $row['total_post'],
            'mq' => (int) $row['mq'],
            'cff' => (int) $row['cff'],
            'em' => (int) $row['em'],
            'phc' => (int) $row['phc'],
            'queue_key' => (string) $row['queue_key'],
        ];
    }

    private function fingerprint(array $snapshot): string
    {
        $canonical = [
            'registration_hash' => (string) data_get($snapshot, 'registration.dataset_hash', ''),
            'circular_version' => (int) data_get($snapshot, 'circular.version', 0),
            'circular_hash' => (string) data_get($snapshot, 'circular.dataset_hash', ''),
            'choice_validation_version' => (int) data_get($snapshot, 'choice_validation.validation_version', 0),
            'choice_validation_hash' => (string) data_get($snapshot, 'choice_validation.dataset_hash', ''),
            'choice_source' => (string) data_get($snapshot, 'choice.source', ''),
            'choice_hash' => (string) data_get($snapshot, 'choice.dataset_hash', ''),
            'tabulation_run_id' => (int) data_get($snapshot, 'tabulation.processing_run_id', 0),
            'tabulation_version' => (int) data_get($snapshot, 'tabulation.processing_version', 0),
            'tabulation_hash' => (string) data_get($snapshot, 'tabulation.dataset_hash', ''),
            'merit_run_id' => (int) data_get($snapshot, 'merit.processing_run_id', 0),
            'merit_version' => (int) data_get($snapshot, 'merit.processing_version', 0),
            'merit_hash' => (string) data_get($snapshot, 'merit.dataset_hash', ''),
            'settings_hash' => (string) data_get($snapshot, 'allocation_settings.dataset_hash', ''),
            'seat_breakup_version' => (int) data_get($snapshot, 'seat_breakup.version', 0),
            'seat_breakup_hash' => (string) data_get($snapshot, 'seat_breakup.dataset_hash', ''),
        ];
        return hash('sha256', json_encode($canonical, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    private function assertSameDirectSources(array $before, array $after): void
    {
        $keys = [
            'circular.dataset_hash', 'choice_validation.dataset_hash', 'choice.source', 'choice.dataset_hash',
            'tabulation.processing_run_id', 'tabulation.dataset_hash', 'merit.processing_run_id', 'merit.dataset_hash', 'allocation_settings.dataset_hash',
            'seat_breakup.version', 'seat_breakup.dataset_hash',
        ];
        foreach ($keys as $key) {
            if ((string) data_get($before, $key, '') !== (string) data_get($after, $key, '')) {
                throw ValidationException::withMessages([
                    'allocation_input' => "DIRECT_INPUT_CHANGED_DURING_FREEZE: {$key} changed while the snapshot was being built. Retry after upstream data is stable.",
                ]);
            }
        }
    }

    private function assertRequiredUpstreamStatuses(): void
    {
        if (! Registration::query()->exists()) {
            throw ValidationException::withMessages(['registration' => 'Registration dataset is missing.']);
        }

        $preliminary = PreliminaryProcessingState::query()->first();
        if ($preliminary?->status !== PreliminaryProcessingStatus::ResultFinalized) {
            throw ValidationException::withMessages(['preliminary' => 'Preliminary result must be finalized before Allocation input freeze.']);
        }

        $written = WrittenProcessingState::query()->first();
        if ($written?->status !== WrittenProcessingStatus::ResultFinalized || (bool) $written?->is_stale) {
            throw ValidationException::withMessages(['written' => 'Written result must be current and finalized before Allocation input freeze.']);
        }

        $viva = VivaProcessingState::query()->first();
        if ($viva?->status !== VivaProcessingStatus::ResultFinalized || (bool) $viva?->is_stale) {
            throw ValidationException::withMessages(['viva' => 'Viva result must be current and finalized before Allocation input freeze.']);
        }
    }

    private function storedMeritSummary(): array
    {
        $state = \App\Models\MeritProcessingState::query()->first();
        if (! $state || (string) $state->status !== 'finalized' || (bool) $state->is_stale || ! $state->latest_finalization_run_id) {
            throw new RuntimeException('A current, non-stale finalized Merit dataset is required.');
        }
        $final = \App\Models\MeritFinalizationRun::query()->find($state->latest_finalization_run_id);
        if (! $final || ! $final->dataset_hash) {
            throw new RuntimeException('Merit finalized hash metadata could not be resolved.');
        }
        return ['dataset_hash' => (string) $final->dataset_hash, 'processing_run_id' => (int) $final->processing_run_id];
    }

    private function storedSeatSummary(): array
    {
        $state = AllocationProcessingState::query()->firstOrCreate(['id' => 1], ['status' => 'not_started']);
        $seat = $state->finalized_seat_breakup_version_id
            ? \App\Models\AllocationSeatBreakupVersion::query()->find($state->finalized_seat_breakup_version_id)
            : null;
        if (! $seat || (string) $seat->status !== 'finalized' || ! $seat->dataset_hash) {
            throw new RuntimeException('A finalized/frozen Seat Breakup is required.');
        }
        return ['dataset_hash' => (string) $seat->dataset_hash, 'version' => (int) $seat->version];
    }
}
