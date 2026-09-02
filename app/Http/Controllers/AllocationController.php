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
use App\Jobs\ProcessAllocationInputFreeze;
use App\Jobs\ProcessAllocationPhaseOne;
use App\Support\Examinations\ExaminationContext;
use App\Services\Allocation\AllocationReadinessService;
use App\Services\Allocation\AllocationSeatBreakupService;
use App\Services\Allocation\AllocationSettingsService;
use App\Services\Allocation\AllocationInputFreezeService;
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
    public function index(AllocationReadinessService $readiness, AllocationSettingsService $settings): View
    {
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
    public function showRun(Request $request, AllocationRun $run): View
    {
        $reg = trim((string) $request->query('reg', ''));
        $cadreCode = trim((string) $request->query('cadre_code', ''));
        $basis = strtoupper(trim((string) $request->query('basis', '')));
        $decisionStatus = strtoupper(trim((string) $request->query('decision_status', '')));

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
         * Seat Ledger must follow the authoritative Circular serial order, not
         * cadre-code order. This keeps the operational review visually aligned
         * with the published vacancy/post-breakup sequence.
         */
        $ledgers = $run->seatLedgers()->with('circularEntry')->get()->sort(function ($left, $right): int {
            $a = $left->circularEntry;
            $b = $right->circularEntry;

            $serialA = (int) ($a?->cadre_serial ?? PHP_INT_MAX);
            $serialB = (int) ($b?->cadre_serial ?? PHP_INT_MAX);
            if ($serialA !== $serialB) {
                return $serialA <=> $serialB;
            }

            $subA = $a?->sub_serial;
            $subB = $b?->sub_serial;
            if ($subA === null && $subB !== null) return -1;
            if ($subA !== null && $subB === null) return 1;
            if ((int) $subA !== (int) $subB) return (int) $subA <=> (int) $subB;

            return (int) $left->id <=> (int) $right->id;
        })->values();

        /*
         * Cadre filter options are derived from the frozen run's own seat ledger.
         * This avoids exposing unrelated master codes that cannot occur in this run.
         */
        $cadreOptions = $ledgers->map(function ($ledger): array {
            $entry = $ledger->circularEntry;
            return [
                'code' => (int) $ledger->cadre_code,
                'title' => (string) ($entry?->post_name_snapshot ?: $entry?->cadre_name_snapshot ?: ''),
            ];
        })->unique('code')->sortBy('code')->values();

        /*
         * Abbreviations are presentation metadata only. Allocation decisions continue
         * to rely on the frozen effective cadre code/circular-entry identity.
         */
        $codes = $ledgers->pluck('cadre_code')->map(fn ($v) => (int) $v)->unique()->values();
        $cadreAbbreviations = \App\Models\CadreMaster::query()
            ->whereIn('cadre_code', $codes)
            ->pluck('cadre_abbr', 'cadre_code')
            ->mapWithKeys(fn ($abbr, $code) => [(int) $code => (string) $abbr]);

        $subAbbreviations = \App\Models\CadreSubMaster::query()
            ->whereIn('sub_cadre_code', $codes)
            ->pluck('sub_cadre_abbr', 'sub_cadre_code')
            ->mapWithKeys(fn ($abbr, $code) => [(int) $code => (string) $abbr]);

        $abbreviationByCode = $cadreAbbreviations->union($subAbbreviations);

        return view('allocation.run-show', compact(
            'run', 'results', 'ledgers', 'reg', 'cadreCode', 'basis', 'decisionStatus',
            'cadreOptions', 'abbreviationByCode'
        ));
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
}
