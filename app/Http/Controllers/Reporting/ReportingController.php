<?php

namespace App\Http\Controllers\Reporting;

use App\Http\Controllers\Controller;
use App\Services\Allocation\AllocationA6ReadinessService;
use App\Support\Examinations\ExaminationContext;
use Illuminate\View\View;

final class ReportingController extends Controller
{
    public function index(ExaminationContext $context, AllocationA6ReadinessService $readiness): View
    {
        return view('reporting.index', [
            'examination' => $context->current(),
            'gate' => $readiness->inspect(),
        ]);
    }
}
