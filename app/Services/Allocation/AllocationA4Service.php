<?php

namespace App\Services\Allocation;

use App\Models\AllocationA4MovementEvent;
use App\Models\AllocationA4Result;
use App\Models\AllocationA4Run;
use App\Models\AllocationA4SeatLedger;
use App\Models\AllocationInputCandidate;
use App\Models\AllocationInputQueueEntry;
use App\Models\AllocationProcessingAudit;
use App\Models\AllocationProcessingState;
use App\Models\AllocationResult;
use App\Models\AllocationRun;
use App\Models\AllocationSeatLedger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * A4 monotonic NM/Shifting engine.
 *
 * LOCKED business boundary:
 * - A3 rows are immutable evidence. This service reads A3 but never updates it.
 * - A3 FINAL (1st-choice MQ) candidates are locked.
 * - A3 TEMPORARY candidates retain their current allocation as a fallback and
 *   may compete only for HIGHER choices; a quota holder also gets one same-cadre
 *   quota->merit normalization opportunity.
 * - NON-ALLOCATED candidates may compete through all frozen choices.
 * - CFF/EM/PHC priority and entitlement are deliberately NOT consulted in A4.
 *   Vacant/released quota capacity is pure merit/NM capacity; quota identity is
 *   retained only as conversion provenance for audit.
 */
final class AllocationA4Service
{
    public function __construct(private readonly AllocationInputFreezeService $inputFreeze) {}

    public function process(AllocationA4Run $a4Run, ?callable $progress = null): AllocationA4Run
    {
        $this->progress($progress, 'VERIFYING_A3', 3, 'Verifying exact immutable A3 source and frozen A2 input.');
        $source = $this->loadVerifiedSource($a4Run);

        $this->progress($progress, 'CONVERTING_QUOTA_VACANCIES', 9, 'Converting A3 vacant quota seats to pure Merit/NM capacity.');
        $solution = $this->solve($source, $progress, true);

        // Determinism is checked entirely in memory before any A4 table is committed.
        $this->progress($progress, 'DETERMINISM_CHECK', 82, 'Replaying A4 against the same immutable A3/A2 source.');
        // Replay must capture the same canonical movement stream as the primary solve.
        // captureEvents=false would make movement_hash compare a populated stream with an empty one.
        $replay = $this->solve($source, null, true);
        foreach (['output_hash', 'seat_ledger_hash', 'movement_hash'] as $hashKey) {
            if (! hash_equals((string) $solution[$hashKey], (string) $replay[$hashKey])) {
                throw new RuntimeException("ALLOCATION_A4_NON_DETERMINISTIC_OUTPUT: {$hashKey} differs on replay. Commit blocked.");
            }
        }

        $this->assertInvariants($source, $solution);

        // A4 can be long-running. Re-read A3 and A2 before commit so an operator
        // can never publish a result built on a source changed during processing.
        $this->progress($progress, 'REVERIFYING_SOURCE', 89, 'Re-verifying A3 immutability and A2 fingerprint before A4 commit.');
        $after = $this->loadVerifiedSource($a4Run);
        if (! hash_equals($source['a3_results_hash'], $after['a3_results_hash'])
            || ! hash_equals($source['a3_ledgers_hash'], $after['a3_ledgers_hash'])) {
            throw new RuntimeException('ALLOCATION_A3_CHANGED_DURING_A4: A3 evidence changed while A4 was running. Commit blocked.');
        }

        $this->progress($progress, 'COMMITTING_A4', 94, 'Committing A4 output, seat ledger and complete movement audit separately from A3.');

        return DB::connection('exam')->transaction(function () use ($a4Run, $solution): AllocationA4Run {
            $locked = AllocationA4Run::query()->whereKey($a4Run->id)->lockForUpdate()->firstOrFail();
            if ((string) $locked->status !== 'running') {
                throw new RuntimeException('Allocation A4 run is no longer RUNNING. Commit blocked.');
            }

            // Defensive retry cleanup affects this A4 run only. A3 tables are never touched.
            AllocationA4Result::query()->where('allocation_a4_run_id', $locked->id)->delete();
            AllocationA4SeatLedger::query()->where('allocation_a4_run_id', $locked->id)->delete();
            AllocationA4MovementEvent::query()->where('allocation_a4_run_id', $locked->id)->delete();

            $now = now();
            foreach (array_chunk($solution['results'], 1000) as $chunk) {
                $rows = array_map(fn (array $row): array => $row + [
                    'allocation_a4_run_id' => (int) $locked->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $chunk);
                AllocationA4Result::query()->insert($rows);
            }

            foreach (array_chunk($solution['seat_ledgers'], 500) as $chunk) {
                $rows = array_map(fn (array $row): array => $row + [
                    'allocation_a4_run_id' => (int) $locked->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $chunk);
                AllocationA4SeatLedger::query()->insert($rows);
            }

            foreach (array_chunk($solution['events'], 1000) as $chunk) {
                $rows = array_map(function (array $row) use ($locked, $now): array {
                    // Query-builder bulk insert bypasses Eloquent JSON casts.
                    $row['allocation_a4_run_id'] = (int) $locked->id;
                    $row['actor_id'] = $locked->started_by ? (int) $locked->started_by : null;
                    $row['context'] = isset($row['context'])
                        ? json_encode($row['context'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
                        : null;
                    $row['created_at'] = $now;
                    return $row;
                }, $chunk);
                AllocationA4MovementEvent::query()->insert($rows);
            }

            AllocationA4Run::query()
                ->where('id', '<>', $locked->id)
                ->where('status', 'a4_complete')
                ->update(['status' => 'superseded', 'updated_at' => $now]);

            $c = $solution['counts'];
            $locked->forceFill([
                'status' => 'a4_complete',
                'phase' => 'A4_COMPLETE',
                'iteration_count' => $solution['iteration_count'],
                'progress_percent' => 100,
                'progress_current' => $c['allocated'],
                'progress_total' => $c['candidate_total'],
                'progress_message' => 'A4 reached global fixed point and passed monotonic merit/NM invariants.',
                'allocated_count' => $c['allocated'],
                'unallocated_count' => $c['unallocated'],
                'mq_count' => $c['MQ'], 'cff_count' => $c['CFF'], 'em_count' => $c['EM'], 'phc_count' => $c['PHC'],
                'nm_count' => $c['NM'], 'shifted_count' => $c['SHIFTED'],
                'quota_to_merit_count' => $c['QUOTA_TO_MERIT'],
                'a4_output_hash' => $solution['output_hash'],
                'seat_ledger_hash' => $solution['seat_ledger_hash'],
                'movement_hash' => $solution['movement_hash'],
                'failure_message' => null,
                'completed_at' => $now,
            ])->save();

            $state = AllocationProcessingState::query()->whereKey(1)->lockForUpdate()->first();
            if ($state) {
                $snapshot = (array) ($state->source_snapshot ?? []);
                $snapshot['allocation_a4_run_id'] = (int) $locked->id;
                $snapshot['allocation_a4_run_version'] = (int) $locked->version;
                $snapshot['allocation_a4_output_hash'] = $solution['output_hash'];
                $snapshot['allocation_a4_seat_ledger_hash'] = $solution['seat_ledger_hash'];
                $snapshot['allocation_a4_movement_hash'] = $solution['movement_hash'];
                $state->forceFill([
                    'status' => 'a4_complete', 'phase' => 'A4_COMPLETE', 'progress_percent' => 100,
                    'progress_current' => $c['allocated'], 'progress_total' => $c['candidate_total'],
                    'progress_message' => 'A4 NM/Shifting global fixed point completed.', 'last_error' => null,
                    'source_snapshot' => $snapshot, 'output_hash' => $solution['output_hash'],
                ])->save();
            }

            AllocationProcessingAudit::query()->create([
                'event' => 'ALLOCATION_A4_COMPLETED',
                'actor_id' => $locked->started_by,
                'from_status' => 'a4_running', 'to_status' => 'a4_complete',
                'context' => [
                    'allocation_a4_run_id' => (int) $locked->id,
                    'phase1_run_id' => (int) $locked->phase1_run_id,
                    'counts' => $c,
                    'output_hash' => $solution['output_hash'],
                    'seat_ledger_hash' => $solution['seat_ledger_hash'],
                    'movement_hash' => $solution['movement_hash'],
                ],
                'created_at' => $now,
            ]);

            return $locked->refresh();
        });
    }

    /** Resolve and strictly verify the exact A3 run A4 is allowed to consume. */
    private function loadVerifiedSource(AllocationA4Run $a4Run): array
    {
        $phase1 = AllocationRun::query()->findOrFail($a4Run->phase1_run_id);
        if ((string) $phase1->status !== 'phase1_complete') {
            throw ValidationException::withMessages(['allocation' => 'A4 requires the current completed A3 Phase-1 run.']);
        }
        if (! hash_equals((string) $a4Run->phase1_output_hash, (string) $phase1->phase1_output_hash)
            || ! hash_equals((string) $a4Run->phase1_seat_ledger_hash, (string) $phase1->seat_ledger_hash)) {
            throw new RuntimeException('A4_SOURCE_A3_HASH_MISMATCH: Stored A3 hashes no longer match the selected A3 run.');
        }

        $freeze = $this->inputFreeze->verifiedCurrent();
        if ((int) $freeze->id !== (int) $a4Run->input_freeze_id
            || (int) $freeze->id !== (int) $phase1->input_freeze_id
            || ! hash_equals((string) $freeze->input_fingerprint, (string) $a4Run->input_fingerprint)
            || ! hash_equals((string) $freeze->queue_hash, (string) $a4Run->queue_hash)) {
            throw new RuntimeException('A4_SOURCE_A2_MISMATCH: Current verified frozen A2 input differs from the A3/A4 source fingerprint.');
        }

        $candidateRows = AllocationInputCandidate::query()->where('input_freeze_id', $freeze->id)->orderBy('registration_id')->get();
        $queueRows = AllocationInputQueueEntry::query()->where('input_freeze_id', $freeze->id)
            ->orderBy('registration_id')->orderBy('choice_position')->orderBy('circular_entry_id')->get();
        // A4 reads the immutable A3 snapshot only through AllocationRun relationships.
        // This makes the read-only boundary explicit: A4 never updates/deletes A3 result or ledger rows.
        $a3Results = $phase1->results()->orderBy('registration_id')->get();
        $a3Ledgers = $phase1->seatLedgers()->orderBy('circular_entry_id')->get();

        $resultsHash = $this->hashRows($a3Results->map(fn ($r) => $r->toArray())->all(), [
            'registration_id','cadre_code','choice_position','merit_position','merit_source','allocation_basis','movement_type','decision_status',
        ]);
        $ledgersHash = $this->hashRows($a3Ledgers->map(fn ($r) => $r->toArray())->all(), [
            'circular_entry_id','cadre_code','total_capacity','mq_capacity','cff_capacity','em_capacity','phc_capacity',
            'mq_occupied','cff_occupied','em_occupied','phc_occupied','mq_remaining','cff_remaining','em_remaining','phc_remaining',
        ]);
        if (! hash_equals($resultsHash, (string) $phase1->phase1_output_hash)
            || ! hash_equals($ledgersHash, (string) $phase1->seat_ledger_hash)) {
            throw new RuntimeException('A4_SOURCE_A3_CONTENT_MISMATCH: A3 rows do not reproduce their committed Phase-1 hashes.');
        }

        $candidates = $candidateRows->keyBy('registration_id');
        $choices = [];
        foreach ($queueRows as $q) {
            // Deliberately omit quota-entitlement fields. A4 is pure merit/NM.
            $choices[(int) $q->registration_id][] = [
                'registration_id' => (int) $q->registration_id,
                'circular_entry_id' => (int) $q->circular_entry_id,
                'cadre_code' => (int) $q->cadre_code,
                'cadre_type' => (string) $q->cadre_type,
                'choice_position' => (int) $q->choice_position,
                'merit_position' => (int) $q->merit_position,
                'merit_source' => (string) $q->merit_source,
            ];
        }

        return [
            'phase1' => $phase1,
            'freeze' => $freeze,
            'candidates' => $candidates,
            'choices' => $choices,
            'a3_results' => $a3Results->keyBy('registration_id'),
            'a3_ledgers' => $a3Ledgers->keyBy('circular_entry_id'),
            'a3_results_hash' => $resultsHash,
            'a3_ledgers_hash' => $ledgersHash,
        ];
    }

    /**
     * Solve A4 as a sequence of stable merit-only improvement rounds.
     *
     * Each inner round fills only the merit capacity currently FREE while every
     * existing allocation remains reserved as fallback. Accepted moves are then
     * committed simultaneously, old seats are released, released quota seats are
     * permanently converted, and another round is solved. This avoids a subtle
     * wrong-allocation bug where an early tentative move could destroy a fallback
     * before a later higher-ranked claimant appears in the same merit competition.
     */
    private function solve(array $source, ?callable $progress, bool $captureEvents): array
    {
        $assignments = [];
        $original = [];
        $lockedFinal = [];
        foreach ($source['a3_results'] as $candidateId => $row) {
            $a = $this->assignmentFromA3($row);
            $assignments[(int) $candidateId] = $a;
            $original[(int) $candidateId] = $a;
            if ((string) $row->decision_status === 'FINAL') {
                $lockedFinal[(int) $candidateId] = true;
            }
        }

        $seatMeta = [];
        $converted = [];
        $events = [];
        $sequence = 0;
        foreach ($source['a3_ledgers'] as $entryId => $ledger) {
            $entryId = (int) $entryId;
            $seatMeta[$entryId] = [
                'circular_entry_id' => $entryId, 'cadre_code' => (int) $ledger->cadre_code,
                'total' => (int) $ledger->total_capacity, 'MQ' => (int) $ledger->mq_capacity,
                'CFF' => (int) $ledger->cff_capacity, 'EM' => (int) $ledger->em_capacity, 'PHC' => (int) $ledger->phc_capacity,
            ];
            // A3 fixed point is the conversion boundary: all remaining quota is
            // now generic merit capacity. It will never be offered as quota again.
            $converted[$entryId] = [
                'CFF' => (int) $ledger->cff_remaining,
                'EM' => (int) $ledger->em_remaining,
                'PHC' => (int) $ledger->phc_remaining,
            ];
            if ($captureEvents) {
                foreach (['CFF','EM','PHC'] as $bucket) {
                    $count = $converted[$entryId][$bucket];
                    if ($count > 0) {
                        $events[] = $this->event(++$sequence, 0, 'INITIAL_QUOTA_VACANCY_CONVERTED', null, null, null, null, null,
                            $entryId, (int) $ledger->cadre_code, 'MQ', null, null, 'NM', 'A3_QUOTA_VACANCY_TO_MERIT', $bucket,
                            ['converted_seat_count' => $count]);
                    }
                }
            }
        }

        $movementSteps = 0;
        $quotaToMeritSteps = 0;
        $iteration = 0;
        $maxIterations = max(10, $source['candidates']->count() + array_sum(array_map('count', $source['choices'])) + 50);

        while (true) {
            $iteration++;
            if ($iteration > $maxIterations) {
                throw new RuntimeException('ALLOCATION_A4_CONVERGENCE_GUARD_EXCEEDED: Monotonic improvement rounds exceeded finite bound.');
            }

            $free = $this->freeMeritCapacity($seatMeta, $converted, $assignments);
            $opportunities = $this->buildOpportunities($source, $assignments, $lockedFinal);
            $accepted = $this->stableFillFreeCapacity($opportunities, $free);

            if ($accepted === []) {
                break;
            }

            ksort($accepted, SORT_NUMERIC);
            foreach ($accepted as $candidateId => $target) {
                $old = $assignments[$candidateId] ?? null;
                $sameCadreConversion = $old !== null
                    && (int) $old['circular_entry_id'] === (int) $target['circular_entry_id']
                    && in_array((string) $old['allocation_basis'], ['CFF','EM','PHC'], true);

                if ($old === null) {
                    $movement = 'NM';
                    $reason = 'NON_ALLOCATED_TO_MERIT_NM';
                } elseif ($sameCadreConversion) {
                    $movement = 'NM';
                    $reason = 'QUOTA_TO_MERIT_CONVERSION';
                    $quotaToMeritSteps++;
                } else {
                    if ((int) $target['choice_position'] >= (int) $old['choice_position']) {
                        throw new RuntimeException("INVARIANT_A4_MONOTONICITY_FAILED_BEFORE_COMMIT: Candidate {$candidateId} target is not a higher choice.");
                    }
                    $movement = 'SHIFTED';
                    $reason = 'HIGHER_CHOICE_MERIT_UPGRADE';
                }

                $new = $target + [
                    'allocation_basis' => 'MQ',
                    'movement_type' => $movement,
                    'decision_reason' => $reason,
                ];

                if ($captureEvents) {
                    $events[] = $this->event(++$sequence, $iteration, $sameCadreConversion ? 'QUOTA_TO_MERIT_CONVERSION' : ($old ? 'SHIFTED' : 'NM_ALLOCATED'),
                        $candidateId,
                        $old['circular_entry_id'] ?? null, $old['cadre_code'] ?? null, $old['allocation_basis'] ?? null, $old['choice_position'] ?? null,
                        $new['circular_entry_id'], $new['cadre_code'], 'MQ', $new['choice_position'], $new['merit_position'], $movement, $reason, null,
                        ['merit_source' => $new['merit_source'], 'fallback_preserved_until_commit' => true]);
                }

                // Releasing an occupied quota seat permanently expands merit/NM
                // capacity by exactly one seat. The source bucket is audit-only.
                if ($old && in_array((string) $old['allocation_basis'], ['CFF','EM','PHC'], true)) {
                    $oldEntry = (int) $old['circular_entry_id'];
                    $bucket = (string) $old['allocation_basis'];
                    $converted[$oldEntry][$bucket]++;
                    if ($converted[$oldEntry][$bucket] > $seatMeta[$oldEntry][$bucket]) {
                        throw new RuntimeException("INVARIANT_QUOTA_CONVERTED_MORE_THAN_ONCE_FAILED: {$bucket} conversion exceeded capacity for entry {$oldEntry}.");
                    }
                    if ($captureEvents) {
                        $events[] = $this->event(++$sequence, $iteration, 'RELEASED_QUOTA_SEAT_CONVERTED', $candidateId,
                            $oldEntry, (int) $old['cadre_code'], $bucket, (int) $old['choice_position'],
                            null, null, null, null, null, 'NM', 'RELEASED_QUOTA_TO_MERIT_CAPACITY', $bucket,
                            ['released_by_movement' => $movement, 'released_by_reason' => $reason]);
                    }
                }

                $assignments[$candidateId] = $new;
                $movementSteps++;
            }

            if ($progress) {
                $pct = min(78, 12 + ($iteration * 5));
                $this->progress($progress, 'GLOBAL_FIXED_POINT', $pct,
                    "A4 iteration {$iteration}: committed ".count($accepted).' monotonic merit improvements; rebuilding queues.',
                    $movementSteps, $source['candidates']->count());
            }
        }

        $results = $this->buildResults($source, $assignments, $original);
        $ledgers = $this->buildSeatLedgers($source, $seatMeta, $converted, $assignments, $results, $events);
        $counts = $this->countResults($source, $results, $events);

        return [
            'results' => $results, 'seat_ledgers' => $ledgers, 'events' => $events,
            'counts' => $counts, 'iteration_count' => $iteration,
            'converted' => $converted, 'assignments' => $assignments, 'original' => $original,
            'output_hash' => $this->hashRows($results, [
                'registration_id','cadre_code','choice_position','merit_position','merit_source','allocation_basis','movement_type','decision_status',
                'original_cadre_code','original_choice_position','original_allocation_basis',
            ]),
            'seat_ledger_hash' => $this->hashRows($ledgers, [
                'circular_entry_id','cadre_code','total_capacity','mq_capacity','cff_capacity','em_capacity','phc_capacity',
                'converted_cff','converted_em','converted_phc','merit_capacity','mq_occupied','cff_occupied','em_occupied','phc_occupied',
                'total_occupied','total_remaining','nm_count','shifted_count','quota_to_merit_count',
            ]),
            'movement_hash' => $this->hashRows($events, [
                'sequence_no','iteration_no','event','registration_id','from_cadre_code','from_basis','from_choice_position',
                'to_cadre_code','to_basis','to_choice_position','target_merit_position','movement_type','reason','converted_from',
            ]),
        ];
    }

    private function assignmentFromA3(AllocationResult $row): array
    {
        return [
            'input_candidate_id' => (int) $row->input_candidate_id,
            'registration_id' => (int) $row->registration_id,
            'reg' => (string) $row->reg,
            'circular_entry_id' => (int) $row->circular_entry_id,
            'cadre_code' => (int) $row->cadre_code,
            'cadre_type' => (string) $row->cadre_type,
            'choice_position' => (int) $row->choice_position,
            'merit_position' => (int) $row->merit_position,
            'merit_source' => (string) $row->merit_source,
            'allocation_basis' => (string) $row->allocation_basis,
            'movement_type' => 'DIRECT',
            'decision_reason' => (string) ($row->decision_reason ?: 'A3_RETAINED'),
        ];
    }

    /** Generic merit capacity minus all current MQ occupants. Quota occupants remain reserved separately. */
    private function freeMeritCapacity(array $seatMeta, array $converted, array $assignments): array
    {
        $used = [];
        foreach ($assignments as $a) {
            if ((string) $a['allocation_basis'] === 'MQ') {
                $e = (int) $a['circular_entry_id'];
                $used[$e] = (int) ($used[$e] ?? 0) + 1;
            }
        }
        $free = [];
        foreach ($seatMeta as $entryId => $seat) {
            $meritCapacity = (int) $seat['MQ'] + array_sum($converted[$entryId] ?? []);
            $free[$entryId] = $meritCapacity - (int) ($used[$entryId] ?? 0);
            if ($free[$entryId] < 0) {
                throw new RuntimeException("INVARIANT_NEGATIVE_A4_MERIT_CAPACITY_FAILED: Entry {$entryId} has more MQ occupants than merit capacity.");
            }
        }
        return $free;
    }

    /** Build only legally monotonic A4 opportunities; no quota flags are present or consulted. */
    private function buildOpportunities(array $source, array $assignments, array $lockedFinal): array
    {
        $out = [];
        foreach ($source['candidates'] as $candidateId => $candidate) {
            $candidateId = (int) $candidateId;
            if (isset($lockedFinal[$candidateId])) {
                continue;
            }
            $rows = $source['choices'][$candidateId] ?? [];
            $current = $assignments[$candidateId] ?? null;
            if ($current === null) {
                $allowed = $rows;
            } else {
                $allowed = array_values(array_filter($rows, function (array $q) use ($current): bool {
                    if ((int) $q['choice_position'] < (int) $current['choice_position']) {
                        return true;
                    }
                    return in_array((string) $current['allocation_basis'], ['CFF','EM','PHC'], true)
                        && (int) $q['circular_entry_id'] === (int) $current['circular_entry_id']
                        && (int) $q['choice_position'] === (int) $current['choice_position'];
                }));
            }
            usort($allowed, fn (array $a, array $b): int => [$a['choice_position'], $a['circular_entry_id']] <=> [$b['choice_position'], $b['circular_entry_id']]);
            if ($allowed !== []) {
                $out[$candidateId] = $allowed;
            }
        }
        return $out;
    }

    /** Candidate-proposing deferred acceptance for the CURRENT free merit slots only. */
    private function stableFillFreeCapacity(array $opportunities, array $free): array
    {
        $next = array_fill_keys(array_keys($opportunities), 0);
        $heldByCadre = [];
        $heldByCandidate = [];
        $pending = array_keys($opportunities);

        while ($pending !== []) {
            sort($pending, SORT_NUMERIC);
            $proposals = [];
            foreach ($pending as $candidateId) {
                if (isset($heldByCandidate[$candidateId])) continue;
                $idx = (int) ($next[$candidateId] ?? 0);
                if (! isset($opportunities[$candidateId][$idx])) continue;
                $p = $opportunities[$candidateId][$idx];
                $proposals[(int) $p['circular_entry_id']][$candidateId] = $p;
            }
            if ($proposals === []) break;

            $rejected = [];
            ksort($proposals, SORT_NUMERIC);
            foreach ($proposals as $entryId => $newRows) {
                $contenders = $heldByCadre[$entryId] ?? [];
                foreach ($newRows as $candidateId => $p) $contenders[$candidateId] = $p;
                uasort($contenders, fn (array $a, array $b): int =>
                    [(int) $a['merit_position'], (int) $a['registration_id']]
                    <=> [(int) $b['merit_position'], (int) $b['registration_id']]);

                $keep = array_slice($contenders, 0, max(0, (int) ($free[$entryId] ?? 0)), true);
                foreach ($contenders as $candidateId => $p) {
                    if (! isset($keep[$candidateId])) {
                        unset($heldByCandidate[$candidateId]);
                        $next[$candidateId] = ((int) $next[$candidateId]) + 1;
                        if (isset($opportunities[$candidateId][$next[$candidateId]])) $rejected[$candidateId] = true;
                    }
                }
                $heldByCadre[$entryId] = $keep;
                foreach ($keep as $candidateId => $p) $heldByCandidate[$candidateId] = $p;
            }
            $pending = array_keys($rejected);
        }

        ksort($heldByCandidate, SORT_NUMERIC);
        return $heldByCandidate;
    }

    private function buildResults(array $source, array $assignments, array $original): array
    {
        $rows = [];
        foreach ($assignments as $candidateId => $a) {
            $candidate = $source['candidates']->get($candidateId);
            if (! $candidate) throw new RuntimeException("A4 candidate {$candidateId} missing from frozen input.");
            $orig = $original[$candidateId] ?? null;

            $movement = 'DIRECT'; $reason = 'A3_ALLOCATION_RETAINED';
            if ($orig === null) {
                $movement = 'NM'; $reason = 'NON_ALLOCATED_TO_MERIT_NM';
            } elseif ((int) $orig['circular_entry_id'] !== (int) $a['circular_entry_id']) {
                $movement = 'SHIFTED'; $reason = 'HIGHER_CHOICE_MERIT_UPGRADE';
            } elseif ((string) $orig['allocation_basis'] !== 'MQ' && (string) $a['allocation_basis'] === 'MQ') {
                $movement = 'NM'; $reason = 'QUOTA_TO_MERIT_CONVERSION';
            }

            $rows[] = [
                'input_candidate_id' => (int) $candidate->id,
                'registration_id' => (int) $candidateId,
                'reg' => (string) $candidate->reg,
                'circular_entry_id' => (int) $a['circular_entry_id'],
                'cadre_code' => (int) $a['cadre_code'],
                'cadre_type' => (string) $a['cadre_type'],
                'choice_position' => (int) $a['choice_position'],
                'merit_position' => (int) $a['merit_position'],
                'merit_source' => (string) $a['merit_source'],
                'allocation_basis' => (string) $a['allocation_basis'],
                'movement_type' => $movement,
                'decision_status' => 'FINAL',
                'decision_reason' => $reason,
                'original_circular_entry_id' => $orig['circular_entry_id'] ?? null,
                'original_cadre_code' => $orig['cadre_code'] ?? null,
                'original_choice_position' => $orig['choice_position'] ?? null,
                'original_allocation_basis' => $orig['allocation_basis'] ?? null,
            ];
        }
        usort($rows, fn (array $a, array $b): int => [$a['registration_id'], $a['cadre_code']] <=> [$b['registration_id'], $b['cadre_code']]);
        return $rows;
    }

    private function buildSeatLedgers(array $source, array $seatMeta, array $converted, array $assignments, array $results, array $events): array
    {
        $occupied = []; $movementByTarget = [];
        foreach ($results as $r) {
            $e=(int)$r['circular_entry_id']; $b=(string)$r['allocation_basis'];
            $occupied[$e][$b]=(int)($occupied[$e][$b]??0)+1;
            if ($r['movement_type']==='NM') $movementByTarget[$e]['NM']=(int)($movementByTarget[$e]['NM']??0)+1;
            if ($r['movement_type']==='SHIFTED') $movementByTarget[$e]['SHIFTED']=(int)($movementByTarget[$e]['SHIFTED']??0)+1;
            if ($r['decision_reason']==='QUOTA_TO_MERIT_CONVERSION') $movementByTarget[$e]['Q2M']=(int)($movementByTarget[$e]['Q2M']??0)+1;
        }
        ksort($seatMeta,SORT_NUMERIC); $rows=[];
        foreach ($seatMeta as $e=>$seat) {
            $mq=(int)($occupied[$e]['MQ']??0); $cff=(int)($occupied[$e]['CFF']??0); $em=(int)($occupied[$e]['EM']??0); $phc=(int)($occupied[$e]['PHC']??0);
            $total=$mq+$cff+$em+$phc; $meritCap=(int)$seat['MQ']+array_sum($converted[$e]??[]);
            $rows[]=[
                'circular_entry_id'=>(int)$e,'cadre_code'=>(int)$seat['cadre_code'],'total_capacity'=>(int)$seat['total'],
                'mq_capacity'=>(int)$seat['MQ'],'cff_capacity'=>(int)$seat['CFF'],'em_capacity'=>(int)$seat['EM'],'phc_capacity'=>(int)$seat['PHC'],
                'converted_cff'=>(int)($converted[$e]['CFF']??0),'converted_em'=>(int)($converted[$e]['EM']??0),'converted_phc'=>(int)($converted[$e]['PHC']??0),
                'merit_capacity'=>$meritCap,'mq_occupied'=>$mq,'cff_occupied'=>$cff,'em_occupied'=>$em,'phc_occupied'=>$phc,
                'total_occupied'=>$total,'total_remaining'=>(int)$seat['total']-$total,
                'nm_count'=>(int)($movementByTarget[$e]['NM']??0),'shifted_count'=>(int)($movementByTarget[$e]['SHIFTED']??0),'quota_to_merit_count'=>(int)($movementByTarget[$e]['Q2M']??0),
            ];
        }
        return $rows;
    }

    private function countResults(array $source, array $results, array $events): array
    {
        $c=['candidate_total'=>$source['candidates']->count(),'allocated'=>count($results),'unallocated'=>$source['candidates']->count()-count($results),'MQ'=>0,'CFF'=>0,'EM'=>0,'PHC'=>0,'NM'=>0,'SHIFTED'=>0,'QUOTA_TO_MERIT'=>0];
        foreach($results as $r){$c[$r['allocation_basis']]++; if($r['movement_type']==='NM')$c['NM']++; if($r['movement_type']==='SHIFTED')$c['SHIFTED']++;}
        $seen=[]; foreach($events as $e){if($e['reason']==='QUOTA_TO_MERIT_CONVERSION' && $e['registration_id']!==null)$seen[(int)$e['registration_id']]=true;}
        $c['QUOTA_TO_MERIT']=count($seen); return $c;
    }

    /** Hard A4 invariants; any failure blocks the entire A4 commit. */
    private function assertInvariants(array $source, array $solution): void
    {
        $resultsByCandidate=[]; $queueLookup=[];
        foreach($source['choices'] as $cid=>$rows) foreach($rows as $q) $queueLookup[$cid][(int)$q['circular_entry_id']]=$q;
        foreach($solution['results'] as $r){
            $cid=(int)$r['registration_id']; if(isset($resultsByCandidate[$cid])) throw new RuntimeException("INVARIANT_ONE_CADRE_PER_CANDIDATE_FAILED: {$cid}");
            $resultsByCandidate[$cid]=$r; $q=$queueLookup[$cid][(int)$r['circular_entry_id']]??null;
            if(!$q) throw new RuntimeException("INVARIANT_A4_CHOICE_MEMBERSHIP_FAILED: {$cid}");
            if((int)$q['merit_position']!==(int)$r['merit_position'] || (string)$q['merit_source']!==(string)$r['merit_source']) throw new RuntimeException("INVARIANT_A4_MERIT_SOURCE_FAILED: {$cid}");
            $orig=$source['a3_results']->get($cid);
            if($orig && (string)$orig->decision_status==='FINAL'){
                if((int)$orig->circular_entry_id!==(int)$r['circular_entry_id'] || (string)$orig->allocation_basis!==(string)$r['allocation_basis']) throw new RuntimeException("INVARIANT_A3_FINAL_LOCK_FAILED: {$cid}");
            }
            if($orig && (string)$orig->decision_status==='TEMPORARY' && (int)$r['choice_position']>(int)$orig->choice_position) throw new RuntimeException("INVARIANT_A4_DOWNWARD_MOVEMENT_FAILED: {$cid}");
        }

        // Seat conservation and one-time quota conversion.
        foreach($solution['seat_ledgers'] as $l){
            if((int)$l['total_occupied']>(int)$l['total_capacity'] || (int)$l['total_remaining']<0) throw new RuntimeException('INVARIANT_A4_SEAT_CAPACITY_FAILED.');
            foreach(['cff','em','phc'] as $q){ if((int)$l['converted_'.$q]>(int)$l[$q.'_capacity']) throw new RuntimeException('INVARIANT_A4_QUOTA_CONVERSION_OVERFLOW_FAILED.'); }
            if((int)$l['mq_occupied']>(int)$l['merit_capacity']) throw new RuntimeException('INVARIANT_A4_MERIT_CAPACITY_FAILED.');
        }

        // Global fixed-point / higher-choice test: no legal monotonic claimant may
        // remain while a generic merit seat is free in a higher allowed choice.
        $free=$this->freeMeritCapacity(
            collect($source['a3_ledgers'])->mapWithKeys(fn($l,$e)=>[(int)$e=>['MQ'=>(int)$l->mq_capacity,'CFF'=>(int)$l->cff_capacity,'EM'=>(int)$l->em_capacity,'PHC'=>(int)$l->phc_capacity,'total'=>(int)$l->total_capacity,'cadre_code'=>(int)$l->cadre_code]])->all(),
            $solution['converted'], $solution['assignments']);
        $locked=[]; foreach($source['a3_results'] as $cid=>$r) if((string)$r->decision_status==='FINAL')$locked[(int)$cid]=true;
        $remaining=$this->buildOpportunities($source,$solution['assignments'],$locked);
        foreach($remaining as $cid=>$opps){ foreach($opps as $q){ if((int)($free[(int)$q['circular_entry_id']]??0)>0) throw new RuntimeException("INVARIANT_HIGHER_CHOICE_ATTAINABLE_FAILED: Candidate {$cid} still has free merit capacity at choice {$q['choice_position']}."); } }

        // Merit-bypass protection: if a candidate still wants an allowed target,
        // they cannot outrank an MQ occupant there. Retained quota occupants are
        // intentionally excluded because their A3 quota seat remains valid until release.
        $mqByEntry=[]; foreach($solution['results'] as $r) if($r['allocation_basis']==='MQ') $mqByEntry[(int)$r['circular_entry_id']][]=$r;
        foreach($remaining as $cid=>$opps){ foreach($opps as $q){
            foreach($mqByEntry[(int)$q['circular_entry_id']]??[] as $occupant){
                if((int)$q['merit_position'] < (int)$occupant['merit_position']) throw new RuntimeException("INVARIANT_A4_MERIT_BYPASS_FAILED: Candidate {$cid} outranks MQ occupant {$occupant['registration_id']} for cadre {$q['cadre_code']}.");
            }
        }}
    }

    private function event(int $seq,int $iteration,string $event,?int $registrationId,?int $fromEntry,?int $fromCadre,?string $fromBasis,?int $fromChoice,?int $toEntry,?int $toCadre,?string $toBasis,?int $toChoice,?int $merit,?string $movement,?string $reason,?string $convertedFrom,array $context=[]): array
    {
        return ['sequence_no'=>$seq,'iteration_no'=>$iteration,'event'=>$event,'registration_id'=>$registrationId,'from_circular_entry_id'=>$fromEntry,'from_cadre_code'=>$fromCadre,'from_basis'=>$fromBasis,'from_choice_position'=>$fromChoice,'to_circular_entry_id'=>$toEntry,'to_cadre_code'=>$toCadre,'to_basis'=>$toBasis,'to_choice_position'=>$toChoice,'target_merit_position'=>$merit,'movement_type'=>$movement,'reason'=>$reason,'converted_from'=>$convertedFrom,'context'=>$context?:null];
    }

    private function hashRows(array $rows,array $fields): string
    {
        $payload=[]; foreach($rows as $row)$payload[]=array_map(fn(string $f)=>$row[$f]??null,$fields);
        return hash('sha256',json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
    }
    private function progress(?callable $cb,string $phase,int $pct,string $msg,int $current=0,int $total=0): void { if($cb)$cb($phase,$pct,$msg,$current,$total); }
}
