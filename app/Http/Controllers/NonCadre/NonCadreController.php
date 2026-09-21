<?php

namespace App\Http\Controllers\NonCadre;

use App\Http\Controllers\Controller;
use App\Services\NonCadre\NonCadreReadinessService;
use App\Support\Examinations\ExaminationContext;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class NonCadreController extends Controller
{
    public function index(ExaminationContext $context, NonCadreReadinessService $readiness): View
    {
        $gate = $readiness->inspect();
        $state = DB::connection('exam')->table('non_cadre_processing_states')->where('id', 1)->first();
        $effectiveCircular = DB::connection('exam')->table('non_cadre_circular_versions')->where('status', 'finalized')->where('is_stale', false)->orderByDesc('version')->first();
        $effectiveSeatBreakup = DB::connection('exam')->table('non_cadre_seat_breakup_versions')->where('status', 'finalized')->where('is_stale', false)->orderByDesc('version')->first();
        $effectiveChoice = DB::connection('exam')->table('non_cadre_choice_imports')->where('status', 'finalized')->where('is_stale', false)->orderByDesc('version')->first();
        $nc4Final = DB::connection('exam')->table('non_cadre_allocation_runs')
            ->where('status', 'finalized')
            ->where('phase', 'FINALIZED')
            ->where('is_stale', false)
            ->exists();

        $reportingStatus = $nc4Final ? 'available' : 'not_started';

        $stages = [
            ['key' => 'circular', 'code' => 'NC1', 'title' => 'Non-Cadre Circular', 'status' => $state?->circular_status ?? 'not_started'],
            ['key' => 'seat_breakup', 'code' => 'NC2', 'title' => 'Seat Breakup', 'status' => $state?->seat_breakup_status ?? 'not_started'],
            ['key' => 'choice', 'code' => 'NC3', 'title' => 'Choice Validation & Adjustment', 'status' => $state?->choice_status ?? 'not_started'],
            ['key' => 'allocation', 'code' => 'NC4', 'title' => 'Non-Cadre Allocation', 'status' => $state?->allocation_status ?? 'not_started'],
            ['key' => 'reporting', 'code' => 'NC5', 'title' => 'Non-Cadre Reporting', 'status' => $reportingStatus],
        ];

        return view('non-cadre.index', [
            'examination' => $context->current(),
            'gate' => $gate,
            'state' => $state,
            'stages' => $stages,
            'effectiveCircular' => $effectiveCircular,
            'effectiveSeatBreakup' => $effectiveSeatBreakup,
            'effectiveChoice' => $effectiveChoice,
            'nc4Final' => $nc4Final,
        ]);
    }
}
