<?php

namespace App\Http\Controllers;

use App\Models\AllocationProcessingAudit;
use App\Models\AllocationProcessingState;
use App\Models\AllocationSeatBreakupVersion;
use App\Services\Allocation\AllocationReadinessService;
use App\Services\Allocation\AllocationSeatBreakupService;
use App\Services\Allocation\AllocationSettingsService;
use App\Reports\Pdf\AllocationSeatBreakupPdfReport;
use Illuminate\Http\Response;
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
            'settings' => $settings->setting(),
            'state' => AllocationProcessingState::query()->firstOrCreate(['id' => 1], ['status' => 'not_started']),
            'seatVersions' => AllocationSeatBreakupVersion::query()->latest('version')->limit(10)->get(),
            'audits' => AllocationProcessingAudit::query()->latest('id')->limit(10)->get(),
        ]);
    }

    public function finalizeSettings(Request $request, AllocationSettingsService $service): RedirectResponse
    {
        $service->finalize($request->user()?->id);
        return back()->with('success', 'Allocation settings finalized and frozen.');
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
