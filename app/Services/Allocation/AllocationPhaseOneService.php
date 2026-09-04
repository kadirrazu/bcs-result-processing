<?php

namespace App\Services\Allocation;

use App\Models\AllocationDecisionEvent;
use App\Models\AllocationInputCandidate;
use App\Models\AllocationInputQueueEntry;
use App\Models\AllocationProcessingAudit;
use App\Models\AllocationProcessingState;
use App\Models\AllocationResult;
use App\Models\AllocationRun;
use App\Models\AllocationSeatLedger;
use App\Models\AllocationSeatBreakupVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * A3 Phase-1 allocation engine.
 *
 * Business boundary:
 * - This service allocates only the original frozen MQ/CFF/EM/PHC capacities.
 * - It DOES NOT convert vacant quota seats to merit/NM and DOES NOT perform the
 *   later NM/shifting phase. Those operations belong to A4.
 * - Phase-1 itself must still reach a stable fixed point before A4 can begin.
 *
 * Algorithm:
 * Candidates propose to Allocation-ready choices in preference order. Each
 * cadre temporarily holds the best candidates by its own merit queue: MQ first,
 * then quota buckets in the configured priority. A displaced/rejected candidate
 * proposes to the next choice. The process stops only when no further proposal
 * is possible. This makes "higher-choice quota beats lower-choice MQ" emerge
 * naturally: a candidate never moves to a lower choice while a higher choice is
 * being held under a valid quota basis.
 */
final class AllocationPhaseOneService
{
    public function __construct(
        private readonly AllocationInputFreezeService $inputFreeze,
        private readonly AllocationSettingsService $settings,
        private readonly AllocationRunStaleService $runStale,
    ) {}

    /**
     * Run Phase-1 against the immutable A2 input snapshot and atomically commit
     * results only after strict source re-verification and hard invariants pass.
     */
    public function process(AllocationRun $run, ?callable $progress = null): AllocationRun
    {
        $this->progress($progress, 'VERIFYING_INPUT', 3, 'Strictly verifying frozen Allocation input before Phase-1.');
        $freeze = $this->inputFreeze->verifiedCurrent();

        if ((int) $freeze->id !== (int) $run->input_freeze_id) {
            throw ValidationException::withMessages([
                'allocation' => 'ALLOCATION_RUN_INPUT_FREEZE_CHANGED: The queued run no longer points to the current frozen input. Start a new run.',
            ]);
        }

        $setting = $this->settings->verified();
        $quotaPriority = array_values((array) $setting->quota_priority);

        $this->progress($progress, 'LOADING_FROZEN_QUEUES', 8, 'Loading immutable candidates and deterministic cadre queues.');
        $input = $this->loadInput($freeze);

        $this->progress($progress, 'PHASE1_FIXED_POINT', 12, 'Running MQ + quota deferred allocation to Phase-1 fixed point.', 0, count($input['candidates']));
        $solution = $this->solve($input, $quotaPriority, $progress);

        // A determinism self-check is intentionally performed before any result
        // commit. The same frozen input/settings must produce the same output.
        $this->progress($progress, 'DETERMINISM_CHECK', 80, 'Replaying Phase-1 in memory to verify deterministic output.');
        $replay = $this->solve($input, $quotaPriority, null, false);
        if (! hash_equals($solution['output_hash'], $replay['output_hash'])
            || ! hash_equals($solution['seat_ledger_hash'], $replay['seat_ledger_hash'])) {
            throw new RuntimeException('ALLOCATION_PHASE1_NON_DETERMINISTIC_OUTPUT: Identical frozen input produced a different replay hash. Commit blocked.');
        }

        $this->assertInvariants($input, $solution);

        // Strictly verify direct sources again after the potentially long engine
        // pass. Any upstream/frozen-queue mutation invalidates this run.
        $this->progress($progress, 'REVERIFYING_INPUT', 87, 'Re-verifying frozen input fingerprint and queue hash before commit.');
        $after = $this->inputFreeze->verifiedCurrent();
        if ((int) $after->id !== (int) $freeze->id
            || ! hash_equals((string) $run->input_fingerprint, (string) $after->input_fingerprint)
            || ! hash_equals((string) $run->queue_hash, (string) $after->queue_hash)) {
            throw ValidationException::withMessages([
                'allocation' => 'ALLOCATION_INPUT_CHANGED_DURING_PHASE1: Frozen inputs changed while Phase-1 was running. Result commit blocked.',
            ]);
        }

        $this->progress($progress, 'COMMITTING_PHASE1', 92, 'Committing verified Phase-1 results, seat ledger and decision events.');

        $completed = DB::connection('exam')->transaction(function () use ($run, $freeze, $solution): AllocationRun {
            $lockedRun = AllocationRun::query()->whereKey($run->id)->lockForUpdate()->firstOrFail();
            $state = AllocationProcessingState::query()->whereKey(1)->lockForUpdate()->firstOrFail();

            if ((string) $lockedRun->status !== 'running') {
                throw new RuntimeException('Allocation Phase-1 run is no longer in RUNNING state. Commit blocked.');
            }

            // Result tables are run-versioned. This delete is defensive for a
            // failed/retried queue attempt of the SAME run, never for old runs.
            AllocationResult::query()->where('allocation_run_id', $lockedRun->id)->delete();
            AllocationSeatLedger::query()->where('allocation_run_id', $lockedRun->id)->delete();
            AllocationDecisionEvent::query()->where('allocation_run_id', $lockedRun->id)->delete();

            $now = now();
            foreach (array_chunk($solution['results'], 1000) as $chunk) {
                $rows = array_map(fn (array $row): array => $row + [
                    'allocation_run_id' => (int) $lockedRun->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $chunk);
                AllocationResult::query()->insert($rows);
            }

            foreach (array_chunk($solution['seat_ledgers'], 500) as $chunk) {
                $rows = array_map(fn (array $row): array => $row + [
                    'allocation_run_id' => (int) $lockedRun->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $chunk);
                AllocationSeatLedger::query()->insert($rows);
            }

            foreach (array_chunk($solution['events'], 1000) as $chunk) {
                $rows = array_map(function (array $row) use ($lockedRun, $now): array {
                    /*
                     * Query Builder bulk insert() bypasses Eloquent casts. The
                     * decision-event context is therefore serialized explicitly
                     * before it reaches MySQL. Do NOT use PHP's array-union (+)
                     * here: the event row already contains a `context` key, and
                     * the left-hand value would win, leaving the raw PHP array in
                     * place and causing an "Array to string conversion" error.
                     */
                    $row['allocation_run_id'] = (int) $lockedRun->id;
                    $row['context'] = isset($row['context'])
                        ? json_encode($row['context'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
                        : null;
                    $row['created_at'] = $now;

                    return $row;
                }, $chunk);

                AllocationDecisionEvent::query()->insert($rows);
            }

            $counts = $solution['counts'];

            // Only the latest successfully committed Phase-1 run is current.
            // Older successful runs remain queryable as immutable history.
            AllocationRun::query()
                ->where('id', '<>', $lockedRun->id)
                ->where('status', 'phase1_complete')
                ->update(['status' => 'superseded', 'updated_at' => $now]);

            $lockedRun->forceFill([
                'status' => 'phase1_complete',
                'phase' => 'PHASE1_COMPLETE',
                'iteration_count' => $solution['iteration_count'],
                'allocated_count' => $counts['allocated'],
                'unallocated_count' => $counts['unallocated'],
                'mq_count' => $counts['MQ'],
                'cff_count' => $counts['CFF'],
                'em_count' => $counts['EM'],
                'phc_count' => $counts['PHC'],
                'final_count' => $counts['FINAL'],
                'temporary_count' => $counts['TEMPORARY'],
                'phase1_output_hash' => $solution['output_hash'],
                'seat_ledger_hash' => $solution['seat_ledger_hash'],
                'failure_message' => null,
                'completed_at' => $now,
            ])->save();

            // A3 completion is deliberately not overall Allocation finalization.
            // A4 must consume this exact run and handle quota->NM/shifting.
            $snapshot = (array) ($state->source_snapshot ?? []);
            $snapshot['allocation_run_id'] = (int) $lockedRun->id;
            $snapshot['allocation_run_version'] = (int) $lockedRun->version;
            $snapshot['phase1_output_hash'] = $solution['output_hash'];
            $snapshot['phase1_seat_ledger_hash'] = $solution['seat_ledger_hash'];

            $state->forceFill([
                'status' => 'phase1_complete',
                'phase' => 'PHASE1_COMPLETE',
                'progress_percent' => 100,
                'progress_current' => $counts['allocated'],
                'progress_total' => count($solution['candidate_ids']),
                'progress_message' => 'Phase-1 MQ + quota allocation reached fixed point and passed invariants.',
                'last_error' => null,
                'source_snapshot' => $snapshot,
                'output_hash' => $solution['output_hash'],
            ])->save();

            AllocationProcessingAudit::query()->create([
                'event' => 'ALLOCATION_PHASE1_COMPLETED',
                'actor_id' => $lockedRun->started_by,
                'from_status' => 'phase1_running',
                'to_status' => 'phase1_complete',
                'context' => [
                    'allocation_run_id' => (int) $lockedRun->id,
                    'allocation_run_version' => (int) $lockedRun->version,
                    'input_freeze_id' => (int) $freeze->id,
                    'input_fingerprint' => (string) $freeze->input_fingerprint,
                    'queue_hash' => (string) $freeze->queue_hash,
                    'iteration_count' => $solution['iteration_count'],
                    'counts' => $counts,
                    'phase1_output_hash' => $solution['output_hash'],
                    'seat_ledger_hash' => $solution['seat_ledger_hash'],
                ],
                'created_at' => $now,
            ]);

            return $lockedRun->refresh();
        });
    }

    /**
     * Materialize only frozen A2 rows. No live Merit/Registration/Circular data
     * is used by the engine itself; provenance was already frozen and verified.
     */
    private function loadInput(\App\Models\AllocationInputFreeze $freeze): array
    {
        $freezeId = (int) $freeze->id;
        $candidates = AllocationInputCandidate::query()
            ->where('input_freeze_id', $freezeId)
            ->orderBy('registration_id')
            ->get()
            ->keyBy('registration_id');

        $queues = AllocationInputQueueEntry::query()
            ->where('input_freeze_id', $freezeId)
            ->orderBy('registration_id')
            ->orderBy('choice_position')
            ->orderBy('cadre_code')
            ->get();

        $byCandidate = [];

        // Build the ledger baseline from the full frozen Seat Breakup, not only
        // cadres that happen to have queue entries. A4 must be able to see every
        // original vacant quota seat, including a cadre with zero eligible queue
        // candidates in A3.
        $seatVersionNumber = (int) data_get($freeze->source_snapshot, 'seat_breakup.version', 0);
        $seatVersion = AllocationSeatBreakupVersion::query()->where('version', $seatVersionNumber)->first();
        if (! $seatVersion || ! hash_equals((string) $freeze->seat_breakup_hash, (string) $seatVersion->dataset_hash)) {
            throw new RuntimeException('FROZEN_SEAT_BREAKUP_CANNOT_BE_RESOLVED: A2 Seat Breakup provenance is missing or no longer matches its frozen hash.');
        }
        $seatMeta = [];
        foreach ($seatVersion->rows()->orderBy('circular_entry_id')->get() as $seatRow) {
            $seatMeta[(int) $seatRow->circular_entry_id] = [
                'circular_entry_id' => (int) $seatRow->circular_entry_id,
                'cadre_code' => (int) $seatRow->cadre_code,
                'total' => (int) $seatRow->total_post,
                'MQ' => (int) $seatRow->mq,
                'CFF' => (int) $seatRow->cff,
                'EM' => (int) $seatRow->em,
                'PHC' => (int) $seatRow->phc,
            ];
        }

        foreach ($queues as $queue) {
            $row = [
                'registration_id' => (int) $queue->registration_id,
                'circular_entry_id' => (int) $queue->circular_entry_id,
                'cadre_code' => (int) $queue->cadre_code,
                'cadre_type' => (string) $queue->cadre_type,
                'choice_position' => (int) $queue->choice_position,
                'merit_position' => (int) $queue->merit_position,
                'merit_source' => (string) $queue->merit_source,
                'eligible_CFF' => (bool) $queue->eligible_cff,
                'eligible_EM' => (bool) $queue->eligible_em,
                'eligible_PHC' => (bool) $queue->eligible_phc,
            ];
            $byCandidate[(int) $queue->registration_id][] = $row;

            $entryId = (int) $queue->circular_entry_id;
            $meta = [
                'circular_entry_id' => $entryId,
                'cadre_code' => (int) $queue->cadre_code,
                'total' => (int) $queue->total_post,
                'MQ' => (int) $queue->mq,
                'CFF' => (int) $queue->cff,
                'EM' => (int) $queue->em,
                'PHC' => (int) $queue->phc,
            ];
            if (! isset($seatMeta[$entryId]) || $seatMeta[$entryId] !== $meta) {
                throw new RuntimeException("FROZEN_QUEUE_SEAT_METADATA_CONFLICT: Circular entry {$entryId} differs from the frozen Seat Breakup.");
            }
        }

        return [
            'candidates' => $candidates,
            'queues_by_candidate' => $byCandidate,
            'seat_meta' => $seatMeta,
        ];
    }

    /**
     * Deferred allocation to a Phase-1 fixed point.
     *
     * @param bool $captureEvents false for deterministic replay to avoid memory cost.
     */
    private function solve(array $input, array $quotaPriority, ?callable $progress, bool $captureEvents = true): array
    {
        $candidateIds = $input['candidates']->keys()->map(fn ($id) => (int) $id)->all();
        $choices = $input['queues_by_candidate'];
        $seatMeta = $input['seat_meta'];

        $nextChoiceIndex = array_fill_keys($candidateIds, 0);
        $heldByCandidate = [];
        $heldByCadre = [];
        $basisByCandidate = [];
        $events = [];
        $sequence = 0;
        $iteration = 0;
        $pending = array_values(array_filter($candidateIds, fn (int $id): bool => ! empty($choices[$id])));

        // A finite guard makes an accidental non-converging implementation a
        // hard failure rather than silently producing an unstable allocation.
        $maxProposals = max(1, array_sum(array_map('count', $choices)) + count($candidateIds) + 10);
        $proposalCount = 0;

        while ($pending !== []) {
            $iteration++;
            $newProposalsByCadre = [];
            $proposedCandidates = [];

            sort($pending, SORT_NUMERIC);
            foreach ($pending as $candidateId) {
                if (isset($heldByCandidate[$candidateId])) {
                    continue;
                }

                $candidateChoices = $choices[$candidateId] ?? [];
                $idx = (int) ($nextChoiceIndex[$candidateId] ?? 0);
                if (! isset($candidateChoices[$idx])) {
                    if ($captureEvents) {
                        $events[] = $this->event(++$sequence, $iteration, 'CHOICES_EXHAUSTED', $candidateId, null, null, null, 'NO_MORE_ELIGIBLE_CHOICES');
                    }
                    continue;
                }

                $proposal = $candidateChoices[$idx];
                $entryId = (int) $proposal['circular_entry_id'];
                $newProposalsByCadre[$entryId][$candidateId] = $proposal;
                $proposedCandidates[$candidateId] = true;
                $proposalCount++;
                if ($proposalCount > $maxProposals) {
                    throw new RuntimeException('ALLOCATION_PHASE1_CONVERGENCE_GUARD_EXCEEDED: Proposal count exceeded the finite frozen-choice bound.');
                }

                if ($captureEvents) {
                    $events[] = $this->event(++$sequence, $iteration, 'PROPOSED', $candidateId, $entryId, (int) $proposal['cadre_code'], null, 'HIGHEST_UNRESOLVED_CHOICE', [
                        'choice_position' => (int) $proposal['choice_position'],
                        'merit_position' => (int) $proposal['merit_position'],
                        'merit_source' => (string) $proposal['merit_source'],
                    ]);
                }
            }

            if ($newProposalsByCadre === []) {
                break;
            }

            $rejected = [];
            $touchedCadres = array_keys($newProposalsByCadre);
            sort($touchedCadres, SORT_NUMERIC);

            foreach ($touchedCadres as $entryId) {
                $contenders = $heldByCadre[$entryId] ?? [];
                foreach ($newProposalsByCadre[$entryId] as $candidateId => $proposal) {
                    $contenders[$candidateId] = $proposal;
                }

                $selection = $this->selectCadreWinners($contenders, $seatMeta[$entryId] ?? null, $quotaPriority);
                $previousHeldIds = array_keys($heldByCadre[$entryId] ?? []);
                $newHeldIds = array_keys($selection['held']);

                foreach ($previousHeldIds as $candidateId) {
                    if (! isset($selection['held'][$candidateId])) {
                        unset($heldByCandidate[$candidateId], $basisByCandidate[$candidateId]);
                        $nextChoiceIndex[$candidateId] = ((int) $nextChoiceIndex[$candidateId]) + 1;
                        $rejected[$candidateId] = true;
                        if ($captureEvents) {
                            $old = $contenders[$candidateId];
                            $events[] = $this->event(++$sequence, $iteration, 'DISPLACED', $candidateId, $entryId, (int) $old['cadre_code'], null, 'OUTRANKED_AFTER_NEW_PROPOSAL');
                        }
                    }
                }

                foreach ($newProposalsByCadre[$entryId] as $candidateId => $proposal) {
                    if (! isset($selection['held'][$candidateId])) {
                        $nextChoiceIndex[$candidateId] = ((int) $nextChoiceIndex[$candidateId]) + 1;
                        $rejected[$candidateId] = true;
                        if ($captureEvents) {
                            $events[] = $this->event(++$sequence, $iteration, 'REJECTED', $candidateId, $entryId, (int) $proposal['cadre_code'], null, 'NO_AVAILABLE_SEAT_AT_CURRENT_MERIT');
                        }
                    }
                }

                $heldByCadre[$entryId] = $selection['held'];
                foreach ($selection['held'] as $candidateId => $proposal) {
                    $heldByCandidate[$candidateId] = $proposal;
                    $basisByCandidate[$candidateId] = $selection['basis'][$candidateId];
                }
            }

            $pending = array_keys($rejected);
            if ($progress) {
                $resolved = count($candidateIds) - count($pending);
                $pct = 12 + (int) floor(min(1, $proposalCount / max(1, $maxProposals)) * 60);
                $this->progress($progress, 'PHASE1_FIXED_POINT', min(74, $pct), "Phase-1 iteration {$iteration}: resolving candidate proposals.", $resolved, count($candidateIds));
            }
        }

        $results = [];
        foreach ($heldByCandidate as $candidateId => $proposal) {
            $candidate = $input['candidates']->get($candidateId);
            if (! $candidate) {
                throw new RuntimeException("Frozen candidate {$candidateId} cannot be resolved while building Phase-1 output.");
            }
            $basis = (string) $basisByCandidate[$candidateId];
            $isImmediateFinal = (int) $proposal['choice_position'] === 1 && $basis === 'MQ';

            $results[] = [
                'input_candidate_id' => (int) $candidate->id,
                'registration_id' => (int) $candidateId,
                'reg' => (string) $candidate->reg,
                'circular_entry_id' => (int) $proposal['circular_entry_id'],
                'cadre_code' => (int) $proposal['cadre_code'],
                'cadre_type' => (string) $proposal['cadre_type'],
                'choice_position' => (int) $proposal['choice_position'],
                'merit_position' => (int) $proposal['merit_position'],
                'merit_source' => (string) $proposal['merit_source'],
                'allocation_basis' => $basis,
                'movement_type' => 'DIRECT',
                'decision_status' => $isImmediateFinal ? 'FINAL' : 'TEMPORARY',
                'decision_reason' => $isImmediateFinal ? 'FIRST_CHOICE_MQ' : 'AWAITING_A4_NM_SHIFTING',
            ];

            if ($captureEvents) {
                $events[] = $this->event(++$sequence, $iteration, 'PHASE1_ASSIGNMENT', $candidateId, (int) $proposal['circular_entry_id'], (int) $proposal['cadre_code'], $basis, $isImmediateFinal ? 'FIRST_CHOICE_MQ_FINAL' : 'TEMPORARY_PENDING_A4', [
                    'choice_position' => (int) $proposal['choice_position'],
                    'merit_position' => (int) $proposal['merit_position'],
                    'decision_status' => $isImmediateFinal ? 'FINAL' : 'TEMPORARY',
                ]);
            }
        }

        usort($results, fn (array $a, array $b): int => [$a['registration_id'], $a['cadre_code']] <=> [$b['registration_id'], $b['cadre_code']]);
        $seatLedgers = $this->buildSeatLedgers($seatMeta, $results);
        $counts = $this->countResults($candidateIds, $results);

        return [
            'candidate_ids' => $candidateIds,
            'results' => $results,
            'seat_ledgers' => $seatLedgers,
            'events' => $events,
            'counts' => $counts,
            'iteration_count' => $iteration,
            'output_hash' => $this->hashRows($results, [
                'registration_id', 'cadre_code', 'choice_position', 'merit_position', 'merit_source',
                'allocation_basis', 'movement_type', 'decision_status',
            ]),
            'seat_ledger_hash' => $this->hashRows($seatLedgers, [
                'circular_entry_id', 'cadre_code', 'total_capacity', 'mq_capacity', 'cff_capacity',
                'em_capacity', 'phc_capacity', 'mq_occupied', 'cff_occupied', 'em_occupied',
                'phc_occupied', 'mq_remaining', 'cff_remaining', 'em_remaining', 'phc_remaining',
            ]),
        ];
    }

    /**
     * Cadre selection is always target-merit ordered. MQ consumes the best
     * contenders first. Remaining contenders may consume exactly one eligible
     * quota bucket, evaluated in the examination's frozen priority order.
     */
    private function selectCadreWinners(array $contenders, ?array $seat, array $quotaPriority): array
    {
        if (! $seat) {
            throw new RuntimeException('Frozen seat metadata is missing for a proposed cadre.');
        }

        uasort($contenders, function (array $a, array $b): int {
            return [(int) $a['merit_position'], (int) $a['registration_id']]
                <=> [(int) $b['merit_position'], (int) $b['registration_id']];
        });

        $held = [];
        $basis = [];
        $remaining = $contenders;

        foreach (array_slice($remaining, 0, (int) $seat['MQ'], true) as $candidateId => $proposal) {
            $held[$candidateId] = $proposal;
            $basis[$candidateId] = 'MQ';
            unset($remaining[$candidateId]);
        }

        foreach ($quotaPriority as $quota) {
            $capacity = (int) ($seat[$quota] ?? 0);
            if ($capacity <= 0) {
                continue;
            }

            $taken = 0;
            foreach ($remaining as $candidateId => $proposal) {
                if (! (bool) ($proposal['eligible_'.$quota] ?? false)) {
                    continue;
                }
                $held[$candidateId] = $proposal;
                $basis[$candidateId] = $quota;
                unset($remaining[$candidateId]);
                $taken++;
                if ($taken >= $capacity) {
                    break;
                }
            }
        }

        return ['held' => $held, 'basis' => $basis];
    }

    private function buildSeatLedgers(array $seatMeta, array $results): array
    {
        $occupied = [];
        foreach ($results as $result) {
            $entryId = (int) $result['circular_entry_id'];
            $basis = (string) $result['allocation_basis'];
            $occupied[$entryId][$basis] = (int) ($occupied[$entryId][$basis] ?? 0) + 1;
        }

        ksort($seatMeta, SORT_NUMERIC);
        $rows = [];
        foreach ($seatMeta as $entryId => $seat) {
            $mq = (int) ($occupied[$entryId]['MQ'] ?? 0);
            $cff = (int) ($occupied[$entryId]['CFF'] ?? 0);
            $em = (int) ($occupied[$entryId]['EM'] ?? 0);
            $phc = (int) ($occupied[$entryId]['PHC'] ?? 0);
            $rows[] = [
                'circular_entry_id' => (int) $entryId,
                'cadre_code' => (int) $seat['cadre_code'],
                'total_capacity' => (int) $seat['total'],
                'mq_capacity' => (int) $seat['MQ'],
                'cff_capacity' => (int) $seat['CFF'],
                'em_capacity' => (int) $seat['EM'],
                'phc_capacity' => (int) $seat['PHC'],
                'mq_occupied' => $mq,
                'cff_occupied' => $cff,
                'em_occupied' => $em,
                'phc_occupied' => $phc,
                'mq_remaining' => (int) $seat['MQ'] - $mq,
                'cff_remaining' => (int) $seat['CFF'] - $cff,
                'em_remaining' => (int) $seat['EM'] - $em,
                'phc_remaining' => (int) $seat['PHC'] - $phc,
            ];
        }
        return $rows;
    }

    private function countResults(array $candidateIds, array $results): array
    {
        $counts = ['allocated' => count($results), 'unallocated' => count($candidateIds) - count($results), 'MQ' => 0, 'CFF' => 0, 'EM' => 0, 'PHC' => 0, 'FINAL' => 0, 'TEMPORARY' => 0];
        foreach ($results as $row) {
            $counts[$row['allocation_basis']]++;
            $counts[$row['decision_status']]++;
        }
        return $counts;
    }

    /** Hard A3 invariants. A single failure blocks all result persistence. */
    private function assertInvariants(array $input, array $solution): void
    {
        $seen = [];
        $queueLookup = [];
        foreach ($input['queues_by_candidate'] as $candidateId => $rows) {
            foreach ($rows as $row) {
                $queueLookup[$candidateId][(int) $row['circular_entry_id']] = $row;
            }
        }

        foreach ($solution['results'] as $result) {
            $candidateId = (int) $result['registration_id'];
            if (isset($seen[$candidateId])) {
                throw new RuntimeException("INVARIANT_ONE_CADRE_PER_CANDIDATE_FAILED: {$candidateId}");
            }
            $seen[$candidateId] = true;

            $queue = $queueLookup[$candidateId][(int) $result['circular_entry_id']] ?? null;
            if (! $queue) {
                throw new RuntimeException("INVARIANT_CHOICE_MEMBERSHIP_FAILED: Candidate {$candidateId} was allocated outside the frozen queue.");
            }
            if ((string) $queue['merit_source'] !== (string) $result['merit_source']
                || (int) $queue['merit_position'] !== (int) $result['merit_position']) {
                throw new RuntimeException("INVARIANT_MERIT_SOURCE_FAILED: Candidate {$candidateId} allocation merit differs from frozen queue merit.");
            }

            $basis = (string) $result['allocation_basis'];
            if ($basis !== 'MQ' && ! (bool) ($queue['eligible_'.$basis] ?? false)) {
                throw new RuntimeException("INVARIANT_QUOTA_ENTITLEMENT_FAILED: Candidate {$candidateId} received {$basis} without entitlement.");
            }

            $shouldFinal = (int) $result['choice_position'] === 1 && $basis === 'MQ';
            if (($shouldFinal ? 'FINAL' : 'TEMPORARY') !== (string) $result['decision_status']) {
                throw new RuntimeException("INVARIANT_TEMP_FINAL_SEMANTICS_FAILED: Candidate {$candidateId} has inconsistent Phase-1 status.");
            }
        }

        $occupiedTotal = 0;
        foreach ($solution['seat_ledgers'] as $ledger) {
            foreach (['mq', 'cff', 'em', 'phc'] as $bucket) {
                if ((int) $ledger[$bucket.'_occupied'] > (int) $ledger[$bucket.'_capacity']) {
                    throw new RuntimeException('INVARIANT_SEAT_CAPACITY_FAILED: Occupancy exceeded frozen bucket capacity.');
                }
                if ((int) $ledger[$bucket.'_remaining'] < 0) {
                    throw new RuntimeException('INVARIANT_NEGATIVE_SEAT_FAILED: A seat ledger bucket became negative.');
                }
                $occupiedTotal += (int) $ledger[$bucket.'_occupied'];
            }
            $capacitySum = (int) $ledger['mq_capacity'] + (int) $ledger['cff_capacity'] + (int) $ledger['em_capacity'] + (int) $ledger['phc_capacity'];
            if ($capacitySum !== (int) $ledger['total_capacity']) {
                throw new RuntimeException('INVARIANT_SEAT_CONSERVATION_FAILED: Frozen bucket capacities do not equal total sanctioned posts.');
            }
        }

        if ($occupiedTotal !== count($solution['results'])) {
            throw new RuntimeException('INVARIANT_LEDGER_RESULT_COUNT_FAILED: Seat occupancy does not match allocated candidate count.');
        }
    }

    private function event(int $sequence, int $iteration, string $event, ?int $registrationId, ?int $entryId, ?int $cadreCode, ?string $basis, ?string $reason, array $context = []): array
    {
        return [
            'sequence_no' => $sequence,
            'iteration_no' => $iteration,
            'phase' => 'PHASE1',
            'event' => $event,
            'registration_id' => $registrationId,
            'circular_entry_id' => $entryId,
            'cadre_code' => $cadreCode,
            'allocation_basis' => $basis,
            'reason' => $reason,
            'context' => $context ?: null,
        ];
    }

    private function hashRows(array $rows, array $fields): string
    {
        $payload = [];
        foreach ($rows as $row) {
            $payload[] = array_map(fn (string $field) => $row[$field] ?? null, $fields);
        }
        return hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    private function progress(?callable $progress, string $phase, int $percent, string $message, int $current = 0, int $total = 0): void
    {
        if ($progress) {
            $progress($phase, $percent, $message, $current, $total);
        }
    }
}
