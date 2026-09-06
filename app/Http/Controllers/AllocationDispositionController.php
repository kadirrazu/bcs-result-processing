<?php

namespace App\Http\Controllers;

use App\Models\AllocationA4Result;
use App\Models\AllocationResultDisposition;
use App\Models\AllocationResultDispositionAudit;
use App\Models\Registration;
use App\Models\User;
use App\Services\Allocation\AllocationA6ReadinessService;
use App\Services\Allocation\AllocationA6ReportService;
use App\Services\Allocation\AllocationResultDispositionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AllocationDispositionController extends Controller
{
    public function index(
        Request $request,
        AllocationA6ReadinessService $readiness,
        AllocationA6ReportService $reports,
        AllocationResultDispositionService $dispositions,
    ): View {
        $a5 = $readiness->requireReady();
        $snapshot = $dispositions->snapshot($a5);
        $search = trim((string) $request->query('search', ''));
        $status = strtoupper(trim((string) $request->query('status', 'ALL')));
        if (! in_array($status, ['ALL','ACTIVE','WITHHELD','CANCELLED'], true)) $status = 'ALL';

        $query = AllocationA4Result::query()
            ->where('allocation_a4_results.allocation_a4_run_id', (int) $a5->allocation_a4_run_id)
            ->join('registrations as r', 'r.id', '=', 'allocation_a4_results.registration_id')
            ->leftJoin('allocation_result_dispositions as d', function ($join) use ($a5): void {
                $join->on('d.registration_id', '=', 'allocation_a4_results.registration_id')
                    ->where('d.allocation_a5_run_id', '=', (int) $a5->id);
            })
            ->select(
                'allocation_a4_results.*',
                'r.name as candidate_name',
                'r.user_id as candidate_user_id',
                'd.status as disposition_status',
                'd.reason as disposition_reason',
                'd.reference_no as disposition_reference_no',
                'd.changed_by as disposition_changed_by',
                'd.changed_at as disposition_changed_at',
            );

        if ($search !== '') {
            $query->where(fn ($q) => $q->where('r.reg', 'like', '%'.$search.'%')
                ->orWhere('r.user_id', 'like', '%'.$search.'%')
                ->orWhere('r.name', 'like', '%'.$search.'%'));
        }
        if ($status === 'ACTIVE') $query->where(fn ($q) => $q->whereNull('d.status')->orWhere('d.status', 'ACTIVE'));
        if ($status === 'WITHHELD') $query->where('d.status', 'WITHHELD');
        if ($status === 'CANCELLED') $query->where('d.status', 'CANCELLED');

        $rows = $query->orderBy('allocation_a4_results.cadre_code')->orderBy('allocation_a4_results.merit_position')->orderBy('allocation_a4_results.reg')->paginate(100)->withQueryString();
        $abbr = $reports->abbreviations($rows->pluck('cadre_code'));
        $operators = User::query()->whereIn('id', $rows->pluck('disposition_changed_by')->filter())->pluck('name', 'id');

        return view('allocation.disposition.index', compact('a5','snapshot','rows','search','status','abbr','operators'));
    }

    public function show(
        int $registrationId,
        AllocationA6ReadinessService $readiness,
        AllocationA6ReportService $reports,
        AllocationResultDispositionService $dispositions,
    ): View {
        $a5 = $readiness->requireReady();
        $allocation = AllocationA4Result::query()
            ->where('allocation_a4_run_id', (int) $a5->allocation_a4_run_id)
            ->where('registration_id', $registrationId)->firstOrFail();
        $registration = Registration::query()->findOrFail($registrationId);
        $disposition = AllocationResultDisposition::query()->where('allocation_a5_run_id', $a5->id)->where('registration_id', $registrationId)->first();
        $effectiveStatus = (string) ($disposition?->status ?: AllocationResultDispositionService::ACTIVE);
        $abbr = (string) $reports->abbreviations(collect([(int) $allocation->cadre_code]))->get((int) $allocation->cadre_code, '—');
        $audits = AllocationResultDispositionAudit::query()->where('allocation_a5_run_id', $a5->id)->where('registration_id', $registrationId)->latest('id')->get();
        $operators = User::query()->whereIn('id', $audits->pluck('actor_id')->filter()->push($disposition?->changed_by)->filter()->unique())->pluck('name', 'id');

        return view('allocation.disposition.show', compact('a5','allocation','registration','disposition','effectiveStatus','abbr','audits','operators'));
    }

    public function update(
        Request $request,
        int $registrationId,
        AllocationA6ReadinessService $readiness,
        AllocationResultDispositionService $dispositions,
    ): RedirectResponse {
        $a5 = $readiness->requireReady();
        $validated = $request->validate([
            'status' => ['required','in:ACTIVE,WITHHELD,CANCELLED'],
            'reason' => ['required','string','max:5000'],
            'reference_no' => ['nullable','string','max:150'],
        ]);

        $dispositions->updateStatus(
            $a5,
            $registrationId,
            (string) $validated['status'],
            (string) $validated['reason'],
            $validated['reference_no'] ?? null,
            $request->user()?->id,
        );

        return redirect()->route('allocation.disposition.show', $registrationId)
            ->with('success', 'Allocation publication status updated. A3/A4/A5 and the allocated seat remain unchanged; prior A6 exports are now outdated.');
    }
}
