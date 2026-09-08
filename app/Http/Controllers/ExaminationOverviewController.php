<?php

namespace App\Http\Controllers;

use App\Services\Overview\ExaminationOverviewService;
use App\Support\Examinations\ExaminationContext;
use Illuminate\View\View;

final class ExaminationOverviewController extends Controller
{
    public function __invoke(ExaminationContext $context, ExaminationOverviewService $overview): View
    {
        return view('overview.index', [
            'examination' => $context->current(),
            ...$overview->build(),
        ]);
    }
}
