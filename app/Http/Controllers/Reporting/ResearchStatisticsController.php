<?php

namespace App\Http\Controllers\Reporting;

use App\Http\Controllers\Controller;
use App\Reports\Pdf\ResearchStatisticsPdfReport;
use App\Services\Reporting\ResearchStatisticsService;
use App\Support\Examinations\ExaminationContext;
use Illuminate\Http\Response;
use Illuminate\View\View;

final class ResearchStatisticsController extends Controller
{
    public function index(ExaminationContext $context, ResearchStatisticsService $statistics): View
    {
        return view('reporting.research-statistics.index', [
            'examination' => $context->current(),
            'catalog' => $statistics->catalog(),
            'availability' => $statistics->availability(),
        ]);
    }

    public function show(string $report, ExaminationContext $context, ResearchStatisticsService $statistics): View
    {
        return view('reporting.research-statistics.show', [
            'examination' => $context->current(),
            'report' => $statistics->report($report),
            'genderNames' => $this->genderNames(),
        ]);
    }

    public function pdf(string $report, ExaminationContext $context, ResearchStatisticsService $statistics, ResearchStatisticsPdfReport $pdf): Response
    {
        $definition = $statistics->report($report);
        $generated = $pdf->generate($definition, (string) $context->current()->name, $this->genderNames());
        return response($generated['content'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$generated['filename'].'"',
        ]);
    }

    /** @return list<string> */
    private function genderNames(): array
    {
        $names = \App\Models\Gender::query()->orderBy('code')->pluck('name')->map(fn ($v) => (string) $v)->values()->all();
        $names[] = 'Unknown / Unmapped Gender';
        return array_values(array_unique($names));
    }
}
