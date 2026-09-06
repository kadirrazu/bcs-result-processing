<?php

namespace App\Http\Controllers;

use App\Models\AllocationProcessingAudit;
use App\Models\AllocationProcessingState;
use App\Models\AllocationInputFreeze;
use App\Models\AllocationInputCandidate;
use App\Models\AllocationSeatBreakupVersion;
use App\Models\AllocationRun;
use App\Models\AllocationResult;
use App\Models\AllocationSeatLedger;
use App\Models\AllocationA4Run;
use App\Models\AllocationA4Result;
use App\Models\AllocationA4SeatLedger;
use App\Models\AllocationA4MovementEvent;
use App\Models\AllocationA5Run;
use App\Models\AllocationA5CandidateResult;
use App\Models\AllocationA5CapacityResult;
use App\Models\CircularEntry;
use App\Models\Registration;
use App\Models\User;
use App\Jobs\ProcessAllocationInputFreeze;
use App\Jobs\ProcessAllocationPhaseOne;
use App\Jobs\ProcessAllocationA4;
use App\Jobs\ProcessAllocationA5;
use App\Support\Examinations\ExaminationContext;
use App\Services\Allocation\AllocationReadinessService;
use App\Services\Allocation\AllocationSeatBreakupService;
use App\Services\Allocation\AllocationSettingsService;
use App\Services\Allocation\AllocationInputFreezeService;
use App\Services\Allocation\AllocationA5ValidityService;
use App\Services\Circular\CircularFinalizedDatasetService;
use App\Reports\Pdf\AllocationSeatBreakupPdfReport;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class AllocationController extends Controller
{
    public function index(AllocationReadinessService $readiness, AllocationSettingsService $settings, \App\Services\Allocation\AllocationRunStaleService $runStale, \App\Services\Allocation\AllocationA6ReadinessService $a6Readiness): View
    {
        // Defensive metadata reconciliation keeps A3/A4 currentness visible even if an upstream change occurred outside a normal UI path.
        $runStale->reconcileCurrentness();

        return view('allocation.index', [
            // Landing page must stay fast. Strict/expensive re-hashing is reserved
            // for the actual server-side pre-run/finalization gate.
            'readiness' => $readiness->inspectDashboard(),
            'settingsInfo' => $settings->dashboard(),
            'settings' => $settings->setting(),
            'state' => AllocationProcessingState::query()->firstOrCreate(['id' => 1], ['status' => 'not_started']),
            'seatVersions' => AllocationSeatBreakupVersion::query()->latest('version')->limit(10)->get(),
            'audits' => AllocationProcessingAudit::query()->latest('id')->limit(10)->get(),
            'inputFreezes' => AllocationInputFreeze::query()->latest('version')->limit(5)->get(),
            'allocationRuns' => AllocationRun::query()->latest('version')->limit(5)->get(),
            // A4 is persisted separately from A3. Landing-page history makes the next phase
            // visible without mutating or hiding the exact Phase-1 evidence.
            'a4Runs' => AllocationA4Run::query()->with('phase1Run')->latest('version')->limit(5)->get(),
            'a5Runs' => AllocationA5Run::query()->with('a4Run')->latest('version')->limit(5)->get(),
            'a6Gate' => $a6Readiness->inspect(),
        ]);
    }


    public function freezeInputs(Request $request, ExaminationContext $context): RedirectResponse
    {
        $examId = $context->currentId();
        abort_if($examId === null, 409, 'No examination selected.');

        $actorId = $request->user()?->id;
        DB::connection('exam')->transaction(function () use ($actorId): void {
            $state = AllocationProcessingState::query()->whereKey(1)->lockForUpdate()->firstOrFail();
            if (in_array((string) $state->status, ['input_freeze_queued', 'input_freeze_running'], true)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'allocation_input' => 'An Allocation input freeze/re-freeze is already queued or running.',
                ]);
            }
            if (in_array((string) $state->status, ['running', 'finalizing', 'finalized', 'phase1_queued', 'phase1_running'], true)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'allocation_input' => 'Allocation input cannot be re-frozen while an Allocation run is running/finalizing/finalized.',
                ]);
            }

            $state->forceFill([
                'status' => 'input_freeze_queued',
                'phase' => 'QUEUED',
                'progress_percent' => 0,
                'progress_current' => 0,
                'progress_total' => 0,
                'progress_message' => 'Allocation input freeze queued.',
                'last_error' => null,
            ])->save();

            AllocationProcessingAudit::query()->create([
                'event' => AllocationInputFreeze::query()->where('status', 'frozen')->exists()
                    ? 'ALLOCATION_INPUT_REFREEZE_QUEUED' : 'ALLOCATION_INPUT_FREEZE_QUEUED',
                'actor_id' => $actorId,
                'from_status' => null,
                'to_status' => 'input_freeze_queued',
                'context' => [],
                'created_at' => now(),
            ]);
        });

        ProcessAllocationInputFreeze::dispatch((int) $examId, $actorId ? (int) $actorId : null);

        return back()->with('success', AllocationInputFreeze::query()->where('status', 'frozen')->exists()
            ? 'Allocation input re-freeze queued. Progress will update below.'
            : 'Allocation input freeze queued. Progress will update below.');
    }

    public function inputFreezeStatus(): JsonResponse
    {
        $state = AllocationProcessingState::query()->firstOrCreate(['id' => 1], ['status' => 'not_started']);
        $freezeId = (int) data_get($state->source_snapshot, 'input_freeze_id', 0);
        $freeze = $freezeId > 0 ? AllocationInputFreeze::query()->find($freezeId) : null;

        return response()->json([
            'status' => (string) $state->status,
            'phase' => (string) ($state->phase ?: ''),
            'progress_percent' => (int) ($state->progress_percent ?? 0),
            'progress_current' => (int) ($state->progress_current ?? 0),
            'progress_total' => (int) ($state->progress_total ?? 0),
            'message' => (string) ($state->progress_message ?: ''),
            'error' => (string) ($state->last_error ?: ''),
            'freeze_id' => $freeze?->id,
            'freeze_version' => $freeze?->version,
            'view_url' => $freeze ? route('allocation.input-freeze.show', $freeze) : null,
        ]);
    }

    public function showInputFreeze(Request $request, AllocationInputFreeze $freeze): View
    {
        $cadreCode = trim((string) $request->query('cadre_code', ''));
        $reg = trim((string) $request->query('reg', ''));

        $candidateIds = null;
        $candidateSearchSummary = null;
        if ($reg !== '') {
            $matchedCandidates = AllocationInputCandidate::query()
                ->where('input_freeze_id', $freeze->id)
                ->where('reg', 'like', '%'.$reg.'%')
                ->orderBy('reg')
                ->limit(50)
                ->get();

            $candidateIds = $matchedCandidates->pluck('registration_id')->unique()->values();
            $matchedQueueRows = $freeze->queueEntries()
                ->whereIn('registration_id', $candidateIds)
                ->orderBy('choice_position')
                ->get(['registration_id', 'cadre_code']);
            $queueCounts = $matchedQueueRows->groupBy('registration_id')->map->count();
            $queueCadres = $matchedQueueRows->groupBy('registration_id')->map(fn ($rows) => $rows->pluck('cadre_code')->unique()->values()->all());

            $candidateSearchSummary = $matchedCandidates->map(function ($candidate) use ($queueCounts, $queueCadres): array {
                return [
                    'registration_id' => (int) $candidate->registration_id,
                    'reg' => (string) $candidate->reg,
                    'category' => (string) ($candidate->cadre_category ?: ''),
                    'queue_count' => (int) ($queueCounts[$candidate->registration_id] ?? 0),
                    'cadres' => $queueCadres[$candidate->registration_id] ?? [],
                ];
            });
        }

        $queueQuery = $freeze->queueEntries()->with('circularEntry');
        if ($cadreCode !== '' && ctype_digit($cadreCode)) {
            $queueQuery->where('cadre_code', (int) $cadreCode);
        }
        if ($candidateIds !== null) {
            $queueQuery->whereIn('registration_id', $candidateIds);
        }

        $queues = $queueQuery
            ->orderBy('cadre_code')
            ->orderBy('merit_position')
            ->orderBy('choice_position')
            ->orderBy('registration_id')
            ->paginate(100)
            ->withQueryString();

        $candidateMap = AllocationInputCandidate::query()
            ->where('input_freeze_id', $freeze->id)
            ->whereIn('registration_id', collect($queues->items())->pluck('registration_id')->unique()->values())
            ->get()
            ->keyBy('registration_id');

        $cadreSummaries = $freeze->queueEntries()
            ->selectRaw('cadre_code, cadre_type, circular_entry_id, COUNT(*) AS queue_count, MAX(total_post) AS total_post, MAX(mq) AS mq, MAX(cff) AS cff, MAX(em) AS em, MAX(phc) AS phc')
            ->groupBy('cadre_code', 'cadre_type', 'circular_entry_id')
            ->get();
        $summaryEntries = \App\Models\CircularEntry::query()
            ->whereIn('id', $cadreSummaries->pluck('circular_entry_id'))
            ->get()->keyBy('id');

        $cadreSummaries = $cadreSummaries->sort(function ($left, $right) use ($summaryEntries): int {
            $a = $summaryEntries->get($left->circular_entry_id);
            $b = $summaryEntries->get($right->circular_entry_id);
            $serialA = (int) ($a?->cadre_serial ?? PHP_INT_MAX);
            $serialB = (int) ($b?->cadre_serial ?? PHP_INT_MAX);
            if ($serialA !== $serialB) return $serialA <=> $serialB;
            $subA = $a?->sub_serial;
            $subB = $b?->sub_serial;
            if ($subA === null && $subB !== null) return -1;
            if ($subA !== null && $subB === null) return 1;
            if ((int) $subA !== (int) $subB) return (int) $subA <=> (int) $subB;
            return (int) $left->cadre_code <=> (int) $right->cadre_code;
        })->values();

        return view('allocation.input-freeze-show', compact(
            'freeze', 'queues', 'candidateMap', 'cadreSummaries', 'summaryEntries',
            'cadreCode', 'reg', 'candidateSearchSummary'
        ));
    }

    public function showCadreQueue(Request $request, AllocationInputFreeze $freeze, \App\Models\CircularEntry $circularEntry): View
    {
        abort_unless($freeze->queueEntries()->where('circular_entry_id', $circularEntry->id)->exists(), 404);

        $reg = trim((string) $request->query('reg', ''));
        $quota = strtoupper(trim((string) $request->query('quota', '')));
        if (! in_array($quota, ['', 'CFF', 'EM', 'PHC'], true)) {
            $quota = '';
        }

        $query = $freeze->queueEntries()
            ->with('circularEntry')
            ->where('circular_entry_id', $circularEntry->id);

        if ($reg !== '') {
            $ids = AllocationInputCandidate::query()
                ->where('input_freeze_id', $freeze->id)
                ->where('reg', 'like', '%'.$reg.'%')
                ->pluck('registration_id');
            $query->whereIn('registration_id', $ids);
        }
        if ($quota !== '') {
            $query->where('eligible_'.strtolower($quota), true);
        }

        $queues = $query
            ->orderBy('merit_position')
            ->orderBy('choice_position')
            ->orderBy('registration_id')
            ->paginate(100)
            ->withQueryString();

        $candidateMap = AllocationInputCandidate::query()
            ->where('input_freeze_id', $freeze->id)
            ->whereIn('registration_id', collect($queues->items())->pluck('registration_id')->unique()->values())
            ->get()
            ->keyBy('registration_id');

        $queueTotal = $freeze->queueEntries()->where('circular_entry_id', $circularEntry->id)->count();

        return view('allocation.input-freeze-cadre-queue', compact(
            'freeze', 'circularEntry', 'queues', 'candidateMap', 'queueTotal', 'reg', 'quota'
        ));
    }



    /**
     * Queue A3 Phase-1 processing. The HTTP request never performs allocation;
     * it only records a versioned run and lets the imports worker execute it.
     */
    public function startPhaseOne(Request $request, ExaminationContext $context): RedirectResponse
    {
        $examId = $context->currentId();
        abort_if($examId === null, 409, 'No examination selected.');
        $actorId = $request->user()?->id;

        $run = DB::connection('exam')->transaction(function () use ($actorId): AllocationRun {
            $state = AllocationProcessingState::query()->whereKey(1)->lockForUpdate()->firstOrFail();
            if (in_array((string) $state->status, ['input_freeze_queued', 'input_freeze_running', 'phase1_queued', 'phase1_running'], true)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'allocation' => 'Another Allocation input/Phase-1 process is already queued or running.',
                ]);
            }
            if ((bool) $state->is_stale) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'allocation' => 'Allocation input is STALE. Re-freeze direct inputs before starting Phase-1.',
                ]);
            }

            $freezeId = (int) data_get($state->source_snapshot, 'input_freeze_id', 0);
            $freeze = $freezeId > 0 ? AllocationInputFreeze::query()->whereKey($freezeId)->where('status', 'frozen')->first() : null;
            if (! $freeze) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'allocation' => 'A current frozen Allocation input is required before Phase-1.',
                ]);
            }

            $nextVersion = ((int) AllocationRun::query()->max('version')) + 1;
            $run = AllocationRun::query()->create([
                'version' => $nextVersion,
                'input_freeze_id' => (int) $freeze->id,
                'status' => 'queued',
                'phase' => 'QUEUED',
                'input_fingerprint' => (string) $freeze->input_fingerprint,
                'queue_hash' => (string) $freeze->queue_hash,
                'settings_hash' => (string) $freeze->settings_hash,
                'seat_breakup_hash' => (string) $freeze->seat_breakup_hash,
                'started_by' => $actorId,
                'started_at' => now(),
            ]);

            $state->forceFill([
                'status' => 'phase1_queued',
                'phase' => 'QUEUED',
                'progress_percent' => 0,
                'progress_current' => 0,
                'progress_total' => 0,
                'progress_message' => 'Allocation Phase-1 MQ + quota processing queued.',
                'last_error' => null,
                'output_hash' => null,
            ])->save();

            AllocationProcessingAudit::query()->create([
                'event' => 'ALLOCATION_PHASE1_QUEUED',
                'actor_id' => $actorId,
                'from_status' => null,
                'to_status' => 'phase1_queued',
                'context' => [
                    'allocation_run_id' => (int) $run->id,
                    'allocation_run_version' => (int) $run->version,
                    'input_freeze_id' => (int) $freeze->id,
                    'input_fingerprint' => (string) $freeze->input_fingerprint,
                    'queue_hash' => (string) $freeze->queue_hash,
                ],
                'created_at' => now(),
            ]);

            return $run;
        });

        ProcessAllocationPhaseOne::dispatch((int) $examId, (int) $run->id, $actorId ? (int) $actorId : null);

        return back()->with('success', "Allocation Phase-1 run v{$run->version} queued. Progress will update below.");
    }

    /** JSON polling endpoint for A3 progress. */
    public function phaseOneStatus(): JsonResponse
    {
        $state = AllocationProcessingState::query()->firstOrCreate(['id' => 1], ['status' => 'not_started']);
        $run = AllocationRun::query()->latest('version')->first();

        return response()->json([
            'status' => (string) $state->status,
            'phase' => (string) ($state->phase ?: ''),
            'progress_percent' => (int) ($state->progress_percent ?? 0),
            'progress_current' => (int) ($state->progress_current ?? 0),
            'progress_total' => (int) ($state->progress_total ?? 0),
            'message' => (string) ($state->progress_message ?: ''),
            'error' => (string) ($state->last_error ?: ''),
            'run_id' => $run?->id,
            'run_version' => $run?->version,
            'view_url' => $run && (string) $run->status === 'phase1_complete' ? route('allocation.runs.show', $run) : null,
        ]);
    }

    /** Review committed A3 output. A4 will later consume this exact run. */
    public function showRun(Request $request, AllocationRun $run, AllocationReadinessService $readiness): View
    {
        $reg = trim((string) $request->query('reg', ''));
        $cadreCode = trim((string) $request->query('cadre_code', ''));
        $basis = strtoupper(trim((string) $request->query('basis', '')));
        $decisionStatus = strtoupper(trim((string) $request->query('decision_status', '')));

        // Seat-ledger review has its own search/filter controls so candidate-result
        // filters do not unexpectedly hide ledger rows (and vice versa).
        $ledgerSearch = strtoupper(trim((string) $request->query('ledger_search', '')));
        $ledgerCadreCode = trim((string) $request->query('ledger_cadre_code', ''));

        if (! in_array($basis, ['', 'MQ', 'CFF', 'EM', 'PHC'], true)) {
            $basis = '';
        }
        if (! in_array($decisionStatus, ['', 'FINAL', 'TEMPORARY'], true)) {
            $decisionStatus = '';
        }

        $query = $run->results()->with('circularEntry');
        if ($reg !== '') {
            $query->where('reg', 'like', '%'.$reg.'%');
        }
        if ($cadreCode !== '' && ctype_digit($cadreCode)) {
            $query->where('cadre_code', (int) $cadreCode);
        }
        if ($basis !== '') {
            $query->where('allocation_basis', $basis);
        }
        if ($decisionStatus !== '') {
            $query->where('decision_status', $decisionStatus);
        }

        $results = $query
            ->orderBy('cadre_code')
            ->orderBy('merit_position')
            ->orderBy('registration_id')
            ->paginate(100)
            ->withQueryString();

        /*
         * Seat Ledger follows the published Circular group first, then serial/sub-serial
         * inside that group. General and Technical sections can both start from serial 1;
         * therefore serial-only sorting would incorrectly interleave the two sections.
         */
        $allLedgers = $run->seatLedgers()->with('circularEntry')->get()->sort(function ($left, $right): int {
            $a = $left->circularEntry;
            $b = $right->circularEntry;
            $typeA = (string) ($a?->cadre_type?->value ?? $a?->cadre_type ?? '');
            $typeB = (string) ($b?->cadre_type?->value ?? $b?->cadre_type ?? '');
            $rank = static fn (string $type): int => match ($type) {
                'GG' => 0,
                'TT' => 1,
                default => 2,
            };
            if ($rank($typeA) !== $rank($typeB)) return $rank($typeA) <=> $rank($typeB);

            $serialA = (int) ($a?->cadre_serial ?? PHP_INT_MAX);
            $serialB = (int) ($b?->cadre_serial ?? PHP_INT_MAX);
            if ($serialA !== $serialB) return $serialA <=> $serialB;

            $subA = $a?->sub_serial;
            $subB = $b?->sub_serial;
            if ($subA === null && $subB !== null) return -1;
            if ($subA !== null && $subB === null) return 1;
            if ((int) $subA !== (int) $subB) return (int) $subA <=> (int) $subB;

            return (int) $left->id <=> (int) $right->id;
        })->values();

        /* Abbreviations are display/filter metadata only; allocation identity remains frozen. */
        $codes = $allLedgers->pluck('cadre_code')->map(fn ($v) => (int) $v)->unique()->values();
        $cadreAbbreviations = \App\Models\CadreMaster::query()
            ->whereIn('cadre_code', $codes)
            ->pluck('cadre_abbr', 'cadre_code')
            ->mapWithKeys(fn ($abbr, $code) => [(int) $code => (string) $abbr]);
        $subAbbreviations = \App\Models\CadreSubMaster::query()
            ->whereIn('sub_cadre_code', $codes)
            ->pluck('sub_cadre_abbr', 'sub_cadre_code')
            ->mapWithKeys(fn ($abbr, $code) => [(int) $code => (string) $abbr]);
        $abbreviationByCode = $cadreAbbreviations->union($subAbbreviations);

        // Both A3 and A4 expose the same operator contract: one free-text Code/Abbr
        // search plus one exact Code - Abbreviation dropdown.
        $ledgerCadreOptions = $allLedgers->map(fn ($ledger) => [
            'code' => (int) $ledger->cadre_code,
            'abbr' => (string) $abbreviationByCode->get((int) $ledger->cadre_code, ''),
        ])->unique('code')->values();

        $ledgers = $allLedgers;
        if ($ledgerSearch !== '') {
            $ledgers = $ledgers->filter(function ($ledger) use ($ledgerSearch, $abbreviationByCode): bool {
                $code = (string) $ledger->cadre_code;
                $abbr = strtoupper((string) $abbreviationByCode->get((int) $ledger->cadre_code, ''));
                return str_contains(strtoupper($code), $ledgerSearch) || str_contains($abbr, $ledgerSearch);
            })->values();
        }
        if ($ledgerCadreCode !== '' && ctype_digit($ledgerCadreCode)) {
            $ledgers = $ledgers->filter(fn ($ledger) => (int) $ledger->cadre_code === (int) $ledgerCadreCode)->values();
        }

        /* Candidate-result cadre filter remains based only on cadres present in this run. */
        $cadreOptions = $allLedgers->map(function ($ledger) use ($abbreviationByCode): array {
            $entry = $ledger->circularEntry;
            return [
                'code' => (int) $ledger->cadre_code,
                'abbr' => (string) $abbreviationByCode->get((int) $ledger->cadre_code, ''),
                'title' => (string) ($entry?->post_name_snapshot ?: $entry?->cadre_name_snapshot ?: ''),
            ];
        })->unique('code')->values();

        // A4 is stored separately; exposing its latest child run here does not alter A3.
        $latestA4Run = AllocationA4Run::query()->where('phase1_run_id', $run->id)->latest('version')->first();
        $a4GateReady = (bool) ($readiness->inspectDashboard()['ready'] ?? false);

        return view('allocation.run-show', compact(
            'run', 'results', 'ledgers', 'reg', 'cadreCode', 'basis', 'decisionStatus',
            'cadreOptions', 'abbreviationByCode', 'latestA4Run', 'a4GateReady',
            'ledgerSearch', 'ledgerCadreCode', 'ledgerCadreOptions'
        ));
    }

    /**
     * Dedicated A3 candidate-result review.
     *
     * A3 Seat Ledger and candidate decisions are intentionally separated so the
     * ledger review stays compact and mirrors the A4 review contract. This method
     * is read-only: it never mutates Phase-1 evidence.
     */
    public function showRunCandidates(Request $request, AllocationRun $run): View
    {
        $reg = trim((string) $request->query('reg', ''));
        $cadreCode = trim((string) $request->query('cadre_code', ''));
        $basis = strtoupper(trim((string) $request->query('basis', '')));
        $decisionStatus = strtoupper(trim((string) $request->query('decision_status', '')));

        if (! in_array($basis, ['', 'MQ', 'CFF', 'EM', 'PHC'], true)) $basis = '';
        if (! in_array($decisionStatus, ['', 'FINAL', 'TEMPORARY'], true)) $decisionStatus = '';

        $query = $run->results()->with('circularEntry');
        if ($reg !== '') $query->where('reg', 'like', '%'.$reg.'%');
        if ($cadreCode !== '' && ctype_digit($cadreCode)) $query->where('cadre_code', (int) $cadreCode);
        if ($basis !== '') $query->where('allocation_basis', $basis);
        if ($decisionStatus !== '') $query->where('decision_status', $decisionStatus);

        $results = $query
            ->orderBy('cadre_code')
            ->orderBy('merit_position')
            ->orderBy('registration_id')
            ->paginate(100)
            ->withQueryString();

        $codes = $run->seatLedgers()->pluck('cadre_code')->map(fn ($v) => (int) $v)->unique()->values();
        $cadreAbbreviations = \App\Models\CadreMaster::query()
            ->whereIn('cadre_code', $codes)->pluck('cadre_abbr', 'cadre_code')
            ->mapWithKeys(fn ($abbr, $code) => [(int) $code => (string) $abbr]);
        $subAbbreviations = \App\Models\CadreSubMaster::query()
            ->whereIn('sub_cadre_code', $codes)->pluck('sub_cadre_abbr', 'sub_cadre_code')
            ->mapWithKeys(fn ($abbr, $code) => [(int) $code => (string) $abbr]);
        $abbreviationByCode = $cadreAbbreviations->union($subAbbreviations);

        $cadreOptions = $run->seatLedgers()->get()->map(fn ($ledger) => [
            'code' => (int) $ledger->cadre_code,
            'abbr' => (string) $abbreviationByCode->get((int) $ledger->cadre_code, ''),
        ])->unique('code')->sortBy('code')->values();

        return view('allocation.run-candidates', compact(
            'run', 'results', 'reg', 'cadreCode', 'basis', 'decisionStatus',
            'cadreOptions', 'abbreviationByCode'
        ));
    }

    /**
     * Read-only A3 cadre drill-down reached from the Phase-1 Seat Ledger.
     * The Circular entry must exist in this exact A3 ledger, so a crafted URL
     * cannot cross into an unrelated cadre/post.
     */
    public function showRunCadreResults(Request $request, AllocationRun $run, CircularEntry $circularEntry): View
    {
        $ledger = $run->seatLedgers()
            ->with('circularEntry')
            ->where('circular_entry_id', (int) $circularEntry->id)
            ->firstOrFail();

        $reg = trim((string) $request->query('reg', ''));
        $basis = strtoupper(trim((string) $request->query('basis', '')));
        $decisionStatus = strtoupper(trim((string) $request->query('decision_status', '')));
        if (! in_array($basis, ['', 'MQ', 'CFF', 'EM', 'PHC'], true)) $basis = '';
        if (! in_array($decisionStatus, ['', 'FINAL', 'TEMPORARY'], true)) $decisionStatus = '';

        $query = $run->results()
            ->where('circular_entry_id', (int) $circularEntry->id)
            ->with('circularEntry');
        if ($reg !== '') $query->where('reg', 'like', '%'.$reg.'%');
        if ($basis !== '') $query->where('allocation_basis', $basis);
        if ($decisionStatus !== '') $query->where('decision_status', $decisionStatus);

        $results = $query->orderBy('merit_position')->orderBy('registration_id')
            ->paginate(100)->withQueryString();

        $code = (int) $ledger->cadre_code;
        $abbr = \App\Models\CadreMaster::query()->where('cadre_code', $code)->value('cadre_abbr')
            ?: \App\Models\CadreSubMaster::query()->where('sub_cadre_code', $code)->value('sub_cadre_abbr')
            ?: '—';

        return view('allocation.run-cadre-results', compact(
            'run', 'ledger', 'circularEntry', 'results', 'reg', 'basis',
            'decisionStatus', 'abbr'
        ));
    }

    /** Queue corrected monotonic A4 against this exact completed A3 run. */
    public function startA4(
        Request $request,
        AllocationRun $run,
        ExaminationContext $context,
        AllocationReadinessService $readiness,
    ): RedirectResponse
    {
        $examId = $context->currentId();
        abort_if($examId === null, 409, 'No examination selected.');

        /*
         * A4 is a downstream result-affecting phase. Never trust a visible button
         * or the old A3 status alone: the same strict Allocation pre-run gate must
         * still be READY at the exact moment A4/Re-Run is requested.
         */
        $gate = $readiness->inspectStrict();
        if (! (bool) ($gate['ready'] ?? false)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'allocation' => 'Allocation Pre-run Gate is BLOCKED. Make all required upstream inputs current/finalized, rebuild the frozen input if required, and re-run A3 before starting A4.',
            ]);
        }

        $actorId = $request->user()?->id;

        $a4Run = DB::connection('exam')->transaction(function () use ($run, $actorId): AllocationA4Run {
            $lockedA3 = AllocationRun::query()->whereKey($run->id)->lockForUpdate()->firstOrFail();
            if ((string) $lockedA3->status !== 'phase1_complete' || (bool) $lockedA3->is_stale) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'allocation' => 'A4 may start only from the current non-stale completed A3 Phase-1 run.',
                ]);
            }
            if (! $lockedA3->phase1_output_hash || ! $lockedA3->seat_ledger_hash) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'allocation' => 'A3 output/seat-ledger hashes are missing. A4 start blocked.',
                ]);
            }
            if (AllocationA4Run::query()->whereIn('status', ['queued', 'running'])->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'allocation' => 'Another A4 run is already queued or running.',
                ]);
            }

            $nextVersion = ((int) AllocationA4Run::query()->max('version')) + 1;
            $a4 = AllocationA4Run::query()->create([
                'version' => $nextVersion,
                'phase1_run_id' => (int) $lockedA3->id,
                'input_freeze_id' => (int) $lockedA3->input_freeze_id,
                'status' => 'queued', 'phase' => 'QUEUED',
                'input_fingerprint' => (string) $lockedA3->input_fingerprint,
                'queue_hash' => (string) $lockedA3->queue_hash,
                'phase1_output_hash' => (string) $lockedA3->phase1_output_hash,
                'phase1_seat_ledger_hash' => (string) $lockedA3->seat_ledger_hash,
                'progress_message' => 'A4 monotonic NM/Shifting queued.',
                'started_by' => $actorId, 'started_at' => now(),
            ]);

            AllocationProcessingState::query()->whereKey(1)->update([
                'status' => 'a4_queued', 'phase' => 'QUEUED', 'progress_percent' => 0,
                'progress_current' => 0, 'progress_total' => 0,
                'progress_message' => 'A4 monotonic NM/Shifting queued.', 'last_error' => null,
            ]);
            AllocationProcessingAudit::query()->create([
                'event' => 'ALLOCATION_A4_QUEUED', 'actor_id' => $actorId,
                'from_status' => 'phase1_complete', 'to_status' => 'a4_queued',
                'context' => [
                    'allocation_a4_run_id' => (int) $a4->id,
                    'phase1_run_id' => (int) $lockedA3->id,
                    'phase1_output_hash' => (string) $lockedA3->phase1_output_hash,
                    'phase1_seat_ledger_hash' => (string) $lockedA3->seat_ledger_hash,
                ],
                'created_at' => now(),
            ]);
            return $a4;
        });

        ProcessAllocationA4::dispatch((int) $examId, (int) $a4Run->id, $actorId ? (int) $actorId : null);
        return redirect()->route('allocation.a4.processing', $a4Run)
            ->with('success', "Allocation A4 run v{$a4Run->version} queued. A3 remains unchanged.");
    }

    /** JSON polling endpoint for A4 progress. */
    public function a4Status(AllocationA4Run $a4Run): JsonResponse
    {
        return response()->json([
            'status' => (string) $a4Run->status,
            'phase' => (string) $a4Run->phase,
            'progress_percent' => (int) $a4Run->progress_percent,
            'progress_current' => (int) $a4Run->progress_current,
            'progress_total' => (int) $a4Run->progress_total,
            'message' => (string) ($a4Run->progress_message ?: ''),
            'error' => (string) ($a4Run->failure_message ?: ''),
            'view_url' => (string) $a4Run->status === 'a4_complete' ? route('allocation.a4.show', $a4Run) : null,
            'processing_url' => route('allocation.a4.processing', $a4Run),
        ]);
    }

    /** Dedicated A4 operational screen; A3 review stays an immutable evidence page. */
    public function showA4Processing(AllocationA4Run $a4Run): View|RedirectResponse
    {
        if ((string) $a4Run->status === 'a4_complete') {
            return redirect()->route('allocation.a4.show', $a4Run);
        }

        return view('allocation.a4-processing', [
            'a4Run' => $a4Run->load('phase1Run'),
        ]);
    }

    /** A4 review mirrors A3 while adding NM/SHIFTED and movement-audit visibility. */
    /**
     * A4 primary review page: intentionally limited to the Seat Ledger.
     * Candidate-level review and movement details live on dedicated pages so the
     * ledger remains compact and easy to scan operationally.
     */
    public function showA4(Request $request, AllocationA4Run $a4Run): View
    {
        $ledgerSearch = strtoupper(trim((string) $request->query('ledger_search', '')));
        $ledgerCadreCode = trim((string) $request->query('ledger_cadre_code', ''));

        $allLedgers = $this->a4LedgersInCircularOrder($a4Run);
        $abbreviationByCode = $this->a4Abbreviations($allLedgers->pluck('cadre_code'));
        $ledgerCadreOptions = $allLedgers->map(fn ($ledger) => [
            'code' => (int) $ledger->cadre_code,
            'abbr' => (string) $abbreviationByCode->get((int) $ledger->cadre_code, ''),
        ])->unique('code')->values();

        // Review-only filters: one free-text Code/Abbreviation search and one exact dropdown.
        $ledgers = $allLedgers;
        if ($ledgerSearch !== '') {
            $ledgers = $ledgers->filter(function ($ledger) use ($ledgerSearch, $abbreviationByCode): bool {
                $code = (string) $ledger->cadre_code;
                $abbr = strtoupper((string) $abbreviationByCode->get((int) $ledger->cadre_code, ''));
                return str_contains(strtoupper($code), $ledgerSearch) || str_contains($abbr, $ledgerSearch);
            })->values();
        }
        if ($ledgerCadreCode !== '' && ctype_digit($ledgerCadreCode)) {
            $ledgers = $ledgers->filter(fn ($ledger) => (int) $ledger->cadre_code === (int) $ledgerCadreCode)->values();
        }

        return view('allocation.a4-show', compact(
            'a4Run', 'ledgers', 'ledgerSearch', 'ledgerCadreCode',
            'ledgerCadreOptions', 'abbreviationByCode'
        ));
    }

    /** Dedicated full A4 candidate-result review with candidate/basis/movement filters. */
    public function showA4Candidates(Request $request, AllocationA4Run $a4Run): View
    {
        $reg = trim((string) $request->query('reg', ''));
        $cadreCode = trim((string) $request->query('cadre_code', ''));
        $basis = strtoupper(trim((string) $request->query('basis', '')));
        $movement = strtoupper(trim((string) $request->query('movement', '')));
        if (! in_array($basis, ['', 'MQ', 'CFF', 'EM', 'PHC'], true)) $basis = '';
        if (! in_array($movement, ['', 'DIRECT', 'NM', 'SHIFTED'], true)) $movement = '';

        $query = $a4Run->results()->with('circularEntry');
        if ($reg !== '') $query->where('reg', 'like', '%'.$reg.'%');
        if ($cadreCode !== '' && ctype_digit($cadreCode)) $query->where('cadre_code', (int) $cadreCode);
        if ($basis !== '') $query->where('allocation_basis', $basis);
        if ($movement !== '') $query->where('movement_type', $movement);

        $results = $query
            ->orderBy('cadre_code')
            ->orderBy('merit_position')
            ->orderBy('registration_id')
            ->paginate(100)
            ->withQueryString();

        $allLedgers = $this->a4LedgersInCircularOrder($a4Run);
        $abbreviationByCode = $this->a4Abbreviations($allLedgers->pluck('cadre_code'));
        $cadreOptions = $allLedgers->map(fn ($ledger) => [
            'code' => (int) $ledger->cadre_code,
            'abbr' => (string) $abbreviationByCode->get((int) $ledger->cadre_code, ''),
            'title' => (string) ($ledger->circularEntry?->post_name_snapshot ?: $ledger->circularEntry?->cadre_name_snapshot ?: ''),
        ])->unique('code')->values();

        $movements = $a4Run->movements()
            ->whereNotNull('registration_id')
            ->latest('sequence_no')
            ->limit(100)
            ->get();

        // Movement audit stores immutable internal IDs. Resolve operator-facing identity
        // in bulk for review only; do not duplicate mutable names/registration values
        // into the allocation evidence rows.
        $movementRegistrationNumbers = Registration::query()
            ->whereIn('id', $movements->pluck('registration_id')->filter()->unique()->values())
            ->pluck('reg', 'id');

        $movementOperators = User::query()
            ->whereIn('id', $movements->pluck('actor_id')->filter()->unique()->values())
            ->get(['id', 'name'])
            ->keyBy('id');

        return view('allocation.a4-candidates', compact(
            'a4Run', 'results', 'reg', 'cadreCode', 'basis', 'movement',
            'cadreOptions', 'abbreviationByCode', 'movements',
            'movementRegistrationNumbers', 'movementOperators'
        ));
    }

    /**
     * Cadre drill-down reached by clicking the Seat Ledger abbreviation.
     * The circular entry is verified against this exact A4 ledger before rows
     * are shown, preventing a URL from crossing into an unrelated cadre/post.
     */
    public function showA4CadreResults(Request $request, AllocationA4Run $a4Run, CircularEntry $circularEntry): View
    {
        $ledger = $a4Run->seatLedgers()
            ->with('circularEntry')
            ->where('circular_entry_id', (int) $circularEntry->id)
            ->firstOrFail();

        $reg = trim((string) $request->query('reg', ''));
        $basis = strtoupper(trim((string) $request->query('basis', '')));
        $movement = strtoupper(trim((string) $request->query('movement', '')));
        if (! in_array($basis, ['', 'MQ', 'CFF', 'EM', 'PHC'], true)) $basis = '';
        if (! in_array($movement, ['', 'DIRECT', 'NM', 'SHIFTED'], true)) $movement = '';

        $query = $a4Run->results()
            ->where('circular_entry_id', (int) $circularEntry->id)
            ->with('circularEntry');
        if ($reg !== '') $query->where('reg', 'like', '%'.$reg.'%');
        if ($basis !== '') $query->where('allocation_basis', $basis);
        if ($movement !== '') $query->where('movement_type', $movement);

        $results = $query
            ->orderBy('merit_position')
            ->orderBy('registration_id')
            ->paginate(100)
            ->withQueryString();

        $abbreviationByCode = $this->a4Abbreviations(collect([(int) $ledger->cadre_code]));
        $cadreAbbreviation = (string) $abbreviationByCode->get((int) $ledger->cadre_code, '—');

        return view('allocation.a4-cadre-results', compact(
            'a4Run', 'ledger', 'circularEntry', 'results', 'reg', 'basis',
            'movement', 'cadreAbbreviation', 'abbreviationByCode'
        ));
    }

    /**
     * Circular order is group-first, then the serial printed inside that group.
     * General and Technical/Professional sections may both legitimately start at
     * serial 1, so sorting by serial alone incorrectly interleaves the sections.
     */
    private function a4LedgersInCircularOrder(AllocationA4Run $a4Run): \Illuminate\Support\Collection
    {
        return $a4Run->seatLedgers()->with('circularEntry')->get()->sort(function ($left, $right): int {
            $a = $left->circularEntry;
            $b = $right->circularEntry;

            $typeA = (string) ($a?->cadre_type?->value ?? $a?->cadre_type ?? '');
            $typeB = (string) ($b?->cadre_type?->value ?? $b?->cadre_type ?? '');
            $rank = static fn (string $type): int => match ($type) {
                'GG' => 0,
                'TT' => 1,
                default => 2,
            };
            if ($rank($typeA) !== $rank($typeB)) return $rank($typeA) <=> $rank($typeB);

            $serialA = (int) ($a?->cadre_serial ?? PHP_INT_MAX);
            $serialB = (int) ($b?->cadre_serial ?? PHP_INT_MAX);
            if ($serialA !== $serialB) return $serialA <=> $serialB;

            $subA = $a?->sub_serial;
            $subB = $b?->sub_serial;
            if ($subA === null && $subB !== null) return -1;
            if ($subA !== null && $subB === null) return 1;
            if ((int) $subA !== (int) $subB) return (int) $subA <=> (int) $subB;

            return (int) $left->id <=> (int) $right->id;
        })->values();
    }

    /** Resolve both cadre and sub-cadre abbreviations against effective codes. */
    private function a4Abbreviations(\Illuminate\Support\Collection $codes): \Illuminate\Support\Collection
    {
        $codes = $codes->map(fn ($value) => (int) $value)->unique()->values();
        $cadres = \App\Models\CadreMaster::query()
            ->whereIn('cadre_code', $codes)
            ->pluck('cadre_abbr', 'cadre_code')
            ->mapWithKeys(fn ($abbr, $code) => [(int) $code => (string) $abbr]);
        $subCadres = \App\Models\CadreSubMaster::query()
            ->whereIn('sub_cadre_code', $codes)
            ->pluck('sub_cadre_abbr', 'sub_cadre_code')
            ->mapWithKeys(fn ($abbr, $code) => [(int) $code => (string) $abbr]);

        return $cadres->union($subCadres);
    }

    public function finalizeSettings(Request $request, AllocationSettingsService $service): RedirectResponse
    {
        $service->finalize($request->user()?->id);
        return back()->with('success', 'Current config/allocation.php values finalized and frozen for this examination.');
    }

    public function seatTemplate(AllocationSeatBreakupService $service): BinaryFileResponse
    {
        $path = $service->templatePath();
        return response()->download($path, 'allocation-seat-breakup.xlsx')->deleteFileAfterSend(true);
    }

    public function uploadSeatBreakup(Request $request, AllocationSeatBreakupService $service): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls', 'max:20480']]);
        $v = $service->import($request->file('file'), $request->user()?->id);
        return back()->with('success', "Seat Breakup v{$v->version} validated. Finalize it before Allocation.");
    }


    public function showSeatBreakup(AllocationSeatBreakupVersion $version): View
    {
        $rows = $version->rows()->with('circularEntry')->get()->sort(function ($left, $right): int {
            $a = $left->circularEntry;
            $b = $right->circularEntry;

            $typeA = (string) ($a?->cadre_type?->value ?? $a?->cadre_type ?? '');
            $typeB = (string) ($b?->cadre_type?->value ?? $b?->cadre_type ?? '');
            if ($typeA !== $typeB) return $typeA <=> $typeB;

            $serialA = (int) ($a?->cadre_serial ?? 0);
            $serialB = (int) ($b?->cadre_serial ?? 0);
            if ($serialA !== $serialB) return $serialA <=> $serialB;

            $subA = $a?->sub_serial;
            $subB = $b?->sub_serial;
            if ($subA === null && $subB !== null) return -1;
            if ($subA !== null && $subB === null) return 1;
            if ((int) $subA !== (int) $subB) return (int) $subA <=> (int) $subB;

            return (int) $left->id <=> (int) $right->id;
        })->values();

        return view('allocation.seat-breakup-show', compact('version', 'rows'));
    }

    public function seatBreakupPdf(AllocationSeatBreakupVersion $version, AllocationSeatBreakupPdfReport $report): Response
    {
        $pdf = $report->generate($version);

        return response($pdf['content'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$pdf['filename'].'"',
            'Content-Length' => (string) strlen($pdf['content']),
        ]);
    }

    public function finalizeSeatBreakup(Request $request, AllocationSeatBreakupVersion $version, AllocationSeatBreakupService $service): RedirectResponse
    {
        $service->finalize($version, $request->user()?->id);
        return back()->with('success', "Seat Breakup v{$version->version} finalized/frozen.");
    }

    /** Queue A5 against the latest current completed A4. A5 never mutates A4. */
    public function startA5(
        Request $request,
        ExaminationContext $context,
        CircularFinalizedDatasetService $circular,
        AllocationReadinessService $readiness,
    ): RedirectResponse {
        $examId = $context->currentId();
        abort_if($examId === null, 409, 'No examination selected.');
        $actorId = $request->user()?->id;

        // A5 is a downstream assurance gate, so source readiness is re-verified
        // server-side; a visible/current A4 card alone is never sufficient.
        $gate = $readiness->inspectStrict();
        if (! (bool) ($gate['ready'] ?? false)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'allocation_a5' => 'A5 start blocked: Allocation upstream readiness/integrity gate is not READY.',
            ]);
        }
        $confirmation = $circular->verifiedConfirmation();

        $a5 = DB::connection('exam')->transaction(function () use ($actorId, $confirmation): AllocationA5Run {
            $a4 = AllocationA4Run::query()
                ->where('status', 'a4_complete')
                ->where('is_stale', false)
                ->latest('version')
                ->lockForUpdate()
                ->first();

            if (! $a4 || ! $a4->a4_output_hash) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'allocation_a5' => 'A5 requires the latest current, non-stale completed A4 Phase-2 result.',
                ]);
            }
            if (AllocationA5Run::query()->whereIn('status', ['queued','running'])->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'allocation_a5' => 'Another A5 validity check is already queued or running.',
                ]);
            }

            // Re-processing never destroys older A5 evidence. A new version is
            // bound to the exact A4 + Circular authority visible at queue time.
            $nextVersion = ((int) AllocationA5Run::query()->max('version')) + 1;
            $run = AllocationA5Run::query()->create([
                'version' => $nextVersion,
                'allocation_a4_run_id' => (int) $a4->id,
                'status' => 'queued', 'phase' => 'QUEUED',
                'a4_output_hash' => (string) $a4->a4_output_hash,
                'circular_version' => (int) $confirmation->version,
                'circular_hash' => (string) $confirmation->dataset_hash,
                'progress_message' => 'A5 Final Allocation Validity Check queued.',
                'started_by' => $actorId, 'started_at' => now(),
            ]);

            AllocationProcessingAudit::query()->create([
                'event' => 'ALLOCATION_A5_QUEUED', 'actor_id' => $actorId,
                'from_status' => 'a4_complete', 'to_status' => 'queued',
                'context' => [
                    'allocation_a5_run_id' => (int) $run->id,
                    'allocation_a4_run_id' => (int) $a4->id,
                    'a4_output_hash' => (string) $a4->a4_output_hash,
                    'circular_version' => (int) $confirmation->version,
                    'circular_hash' => (string) $confirmation->dataset_hash,
                ],
                'created_at' => now(),
            ]);
            return $run;
        });

        ProcessAllocationA5::dispatch((int) $examId, (int) $a5->id, $actorId ? (int) $actorId : null);
        return redirect()->route('allocation.a5.processing', $a5)
            ->with('success', "A5 validity check v{$a5->version} queued. A4 result remains unchanged.");
    }

    public function a5Status(AllocationA5Run $a5Run): JsonResponse
    {
        return response()->json([
            'status' => (string) $a5Run->status,
            'phase' => (string) $a5Run->phase,
            'progress_percent' => (int) $a5Run->progress_percent,
            'progress_current' => (int) $a5Run->progress_current,
            'progress_total' => (int) $a5Run->progress_total,
            'message' => (string) ($a5Run->progress_message ?: ''),
            'error' => (string) ($a5Run->failure_message ?: ''),
            'view_url' => in_array((string) $a5Run->status, ['validated_ok','validated_failed','finalized'], true)
                ? route('allocation.a5.show', $a5Run) : null,
        ]);
    }

    public function showA5Processing(AllocationA5Run $a5Run): View|RedirectResponse
    {
        if (in_array((string) $a5Run->status, ['validated_ok','validated_failed','finalized'], true)) {
            return redirect()->route('allocation.a5.show', $a5Run);
        }
        return view('allocation.a5-processing', ['a5Run' => $a5Run->load('a4Run')]);
    }

    /**
     * A5 summary page is cadre-first. It intentionally keeps candidate detail
     * off this screen so operators can review the final assurance gate exactly
     * like the A4 Seat Ledger: Circular group/serial order, then drill down.
     */
    public function showA5(AllocationA5Run $a5Run): View
    {
        $capacityResults = $a5Run->capacityResults()->get();
        $candidateStats = $a5Run->candidateResults()
            ->selectRaw('circular_entry_id, COUNT(*) AS total_candidates, SUM(CASE WHEN overall_status = ? THEN 1 ELSE 0 END) AS passed_candidates, SUM(CASE WHEN overall_status = ? THEN 1 ELSE 0 END) AS failed_candidates', ['PASS', 'FAIL'])
            ->groupBy('circular_entry_id')
            ->get()
            ->keyBy('circular_entry_id');

        $entries = CircularEntry::query()
            ->whereIn('id', $capacityResults->pluck('circular_entry_id')->all())
            ->get()
            ->keyBy('id');

        // Preserve the same General -> Technical/Professional -> Circular serial
        // order used by the A4 Seat Ledger so A4/A5 can be compared visually.
        $capacityResults = $capacityResults->sort(function ($left, $right) use ($entries): int {
            $a = $entries->get((int) $left->circular_entry_id);
            $b = $entries->get((int) $right->circular_entry_id);
            $typeA = (string) ($a?->cadre_type?->value ?? $a?->cadre_type ?? '');
            $typeB = (string) ($b?->cadre_type?->value ?? $b?->cadre_type ?? '');
            $rank = static fn (string $type): int => match ($type) { 'GG' => 0, 'TT' => 1, default => 2 };
            if ($rank($typeA) !== $rank($typeB)) return $rank($typeA) <=> $rank($typeB);
            if ((int) ($a?->cadre_serial ?? PHP_INT_MAX) !== (int) ($b?->cadre_serial ?? PHP_INT_MAX)) {
                return (int) ($a?->cadre_serial ?? PHP_INT_MAX) <=> (int) ($b?->cadre_serial ?? PHP_INT_MAX);
            }
            $subA = $a?->sub_serial; $subB = $b?->sub_serial;
            if ($subA === null && $subB !== null) return -1;
            if ($subA !== null && $subB === null) return 1;
            if ((int) $subA !== (int) $subB) return (int) $subA <=> (int) $subB;
            return (int) $left->id <=> (int) $right->id;
        })->values();

        $abbreviationByCode = $this->a4Abbreviations($capacityResults->pluck('cadre_code'));

        return view('allocation.a5-show', compact(
            'a5Run', 'capacityResults', 'candidateStats', 'entries', 'abbreviationByCode'
        ));
    }

    /** Separate pre/post-finalized candidate validity report with search/filter. */
    public function showA5Candidates(Request $request, AllocationA5Run $a5Run): View
    {
        $status = strtoupper(trim((string) $request->query('status', '')));
        if (! in_array($status, ['', 'PASS', 'FAIL'], true)) $status = '';

        $search = trim((string) $request->query('search', ''));
        $cadreCode = trim((string) $request->query('cadre_code', ''));
        $allCodes = $a5Run->candidateResults()->distinct()->orderBy('cadre_code')->pluck('cadre_code');
        $abbreviationByCode = $this->a4Abbreviations($allCodes);
        $cadreOptions = $allCodes->map(fn ($code): array => [
            'code' => (int) $code,
            'abbr' => (string) $abbreviationByCode->get((int) $code, ''),
        ])->values();

        $query = $a5Run->candidateResults();
        if ($status !== '') $query->where('overall_status', $status);
        if ($cadreCode !== '' && ctype_digit($cadreCode)) $query->where('cadre_code', (int) $cadreCode);

        if ($search !== '') {
            // Search supports registration number, numeric cadre code, or cadre
            // abbreviation while the exact 110 - ADMN selector remains separate.
            $matchingCodes = $abbreviationByCode
                ->filter(fn ($abbr, $code) => str_contains(strtoupper((string) $abbr), strtoupper($search)) || str_contains((string) $code, $search))
                ->keys()->map(fn ($code) => (int) $code)->all();
            $query->where(function ($q) use ($search, $matchingCodes): void {
                $q->where('reg', 'like', '%'.$search.'%');
                if ($matchingCodes !== []) $q->orWhereIn('cadre_code', $matchingCodes);
                if (ctype_digit($search)) $q->orWhere('cadre_code', (int) $search);
            });
        }

        // The all-candidate report is grouped visually by cadre first, then by
        // the exact A4 target-cadre merit position used for the final allocation.
        // This avoids mixing merit positions from unrelated cadre competitions.
        // Registration number is only the deterministic final fallback.
        $meritPositionSubquery = AllocationA4Result::query()
            ->select('merit_position')
            ->whereColumn('allocation_a4_results.id', 'allocation_a5_candidate_results.allocation_a4_result_id');

        $results = $query
            ->select('allocation_a5_candidate_results.*')
            ->addSelect(['merit_position' => $meritPositionSubquery])
            ->orderBy('cadre_code')
            ->orderBy('merit_position')
            ->orderBy('reg')
            ->paginate(100)
            ->withQueryString();
        return view('allocation.a5-candidates', compact(
            'a5Run', 'results', 'status', 'search', 'cadreCode', 'cadreOptions', 'abbreviationByCode'
        ));
    }

    /** Cadre drill-down from the A5 summary assurance table. */
    public function showA5CadreResults(Request $request, AllocationA5Run $a5Run, CircularEntry $circularEntry): View
    {
        $capacity = $a5Run->capacityResults()->where('circular_entry_id', (int) $circularEntry->id)->firstOrFail();
        $status = strtoupper(trim((string) $request->query('status', '')));
        if (! in_array($status, ['', 'PASS', 'FAIL'], true)) $status = '';
        $reg = trim((string) $request->query('reg', ''));

        $query = $a5Run->candidateResults()->where('circular_entry_id', (int) $circularEntry->id);
        if ($status !== '') $query->where('overall_status', $status);
        if ($reg !== '') $query->where('reg', 'like', '%'.$reg.'%');
        // Cadre drill-down is one competition only, so its natural display
        // order is the exact A4 merit position ascending. Expose that same merit
        // position to the view so the operator can immediately see why rows are
        // ordered this way. Registration number remains the stable fallback.
        $meritPositionSubquery = AllocationA4Result::query()
            ->select('merit_position')
            ->whereColumn('allocation_a4_results.id', 'allocation_a5_candidate_results.allocation_a4_result_id');

        $results = $query
            ->select('allocation_a5_candidate_results.*')
            ->addSelect(['merit_position' => $meritPositionSubquery])
            ->orderBy('merit_position')
            ->orderBy('reg')
            ->paginate(100)
            ->withQueryString();

        $abbreviationByCode = $this->a4Abbreviations(collect([(int) $capacity->cadre_code]));
        $cadreAbbreviation = (string) $abbreviationByCode->get((int) $capacity->cadre_code, '—');
        $candidatePassed = $a5Run->candidateResults()->where('circular_entry_id', (int) $circularEntry->id)->where('overall_status', 'PASS')->count();
        $candidateFailed = $a5Run->candidateResults()->where('circular_entry_id', (int) $circularEntry->id)->where('overall_status', 'FAIL')->count();

        return view('allocation.a5-cadre-results', compact(
            'a5Run', 'circularEntry', 'capacity', 'results', 'status', 'reg',
            'cadreAbbreviation', 'candidatePassed', 'candidateFailed'
        ));
    }

    public function finalizeA5(Request $request, AllocationA5Run $a5Run, AllocationA5ValidityService $service): RedirectResponse
    {
        $finalized = $service->finalize($a5Run, $request->user()?->id ? (int) $request->user()->id : null);
        return redirect()->route('allocation.a5.show', $finalized)
            ->with('success', "A5 v{$finalized->version} finalized. Final Allocation Validity Gate is 100% PASS and ready for Reporting/Export.");
    }

}
