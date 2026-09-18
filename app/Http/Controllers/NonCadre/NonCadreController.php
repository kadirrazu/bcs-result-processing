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

        $stages = [
            ['key' => 'circular', 'code' => 'NC1', 'title' => 'Non-Cadre Circular', 'status' => $state?->circular_status ?? 'not_started'],
            ['key' => 'seat_breakup', 'code' => 'NC2', 'title' => 'Seat Breakup', 'status' => $state?->seat_breakup_status ?? 'not_started'],
            ['key' => 'choice', 'code' => 'NC3', 'title' => 'Choice Validation & Adjustment', 'status' => $state?->choice_status ?? 'not_started'],
            ['key' => 'allocation', 'code' => 'NC4', 'title' => 'Non-Cadre Allocation', 'status' => $state?->allocation_status ?? 'not_started'],
            ['key' => 'reporting', 'code' => 'NC5', 'title' => 'Non-Cadre Reporting', 'status' => $state?->reporting_status ?? 'not_started'],
        ];

        return view('non-cadre.index', [
            'examination' => $context->current(),
            'gate' => $gate,
            'state' => $state,
            'stages' => $stages,
        ]);
    }
}
