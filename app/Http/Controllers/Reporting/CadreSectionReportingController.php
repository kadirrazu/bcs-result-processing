<?php

namespace App\Http\Controllers\Reporting;

use App\Http\Controllers\Controller;
use App\Services\Allocation\AllocationA6ReadinessService;
use App\Services\Reporting\AllocationVerificationReportService;
use App\Support\Examinations\ExaminationContext;
use Illuminate\View\View;

final class CadreSectionReportingController extends Controller
{
    public function index(AllocationA6ReadinessService $readiness, AllocationVerificationReportService $reports): View
    {
        $gate = $readiness->inspect();
        return view('reporting.cadre-section.index', [
            'gate' => $gate,
            'technicalCadres' => $gate['ready'] ? $reports->technicalCadres($gate['a5']) : collect(),
        ]);
    }

    public function verification(
        string $type,
        AllocationA6ReadinessService $readiness,
        AllocationVerificationReportService $reports,
        ExaminationContext $context,
    ): View {
        $a5 = $readiness->requireReady();
        $data = $reports->build($type, $a5);
        return view('reporting.allocation-verification.report', $data + [
            'examination' => $context->current(),
            'reportType' => $type,
        ]);
    }

    public function technicalCadre(
        int $cadreCode,
        AllocationA6ReadinessService $readiness,
        AllocationVerificationReportService $reports,
        ExaminationContext $context,
    ): View {
        $a5 = $readiness->requireReady();
        $data = $reports->build('technical-cadre', $a5, $cadreCode);
        return view('reporting.allocation-verification.report', $data + [
            'examination' => $context->current(),
            'reportType' => 'technical-cadre',
        ]);
    }
}
