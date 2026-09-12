<?php

namespace App\Http\Controllers;

use App\Models\ChoiceOptimizationHistoricalChoice;
use App\Models\CadreMaster;
use App\Models\CadreSubMaster;
use App\Models\CircularEntry;
use App\Models\ManualAllocationChoiceAdjustmentEvent;
use App\Models\Registration;
use App\Services\Allocation\ManualAllocationChoiceAdjustmentService;
use App\Services\ChoiceOptimization\FinalAllocationReadyChoiceService;
use App\Services\Circular\CircularFinalizedDatasetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class ManualAllocationChoiceAdjustmentController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $status = strtolower(trim((string) $request->query('status', 'all')));
        if (! in_array($status, ['all', 'adjusted', 'unchanged'], true)) {
            $status = 'all';
        }

        $adjustedIds = ManualAllocationChoiceAdjustmentEvent::query()
            ->distinct()
            ->pluck('registration_id')
            ->map(fn ($v) => (int) $v)
            ->all();

        $query = ChoiceOptimizationHistoricalChoice::query()
            ->join('registrations as r', 'r.id', '=', 'choice_optimization_historical_choices.registration_id')
            ->select('choice_optimization_historical_choices.*', 'r.name as candidate_name');
        if ($search !== '') {
            $query->where(fn ($q) => $q->where('r.reg', 'like', '%'.$search.'%')->orWhere('r.name', 'like', '%'.$search.'%'));
        }

        if ($status === 'adjusted') {
            $query->whereIn('choice_optimization_historical_choices.registration_id', $adjustedIds ?: [-1]);
        } elseif ($status === 'unchanged' && $adjustedIds !== []) {
            $query->whereNotIn('choice_optimization_historical_choices.registration_id', $adjustedIds);
        }

        $rows = $query->orderBy('r.reg')->paginate(100)->withQueryString();
        $choiceCodeAbbrMap = $this->choiceCodeAbbrMap();

        return view('choice-optimization.manual-adjustment.index', compact(
            'rows','search','status','adjustedIds','choiceCodeAbbrMap'
        ));
    }

    public function show(
        int $registrationId,
        FinalAllocationReadyChoiceService $finalChoices,
        CircularFinalizedDatasetService $circular,
    ): View {
        $registration = Registration::query()->findOrFail($registrationId);
        $choice = ChoiceOptimizationHistoricalChoice::query()->where('registration_id', $registrationId)->firstOrFail();
        $baseCodes = array_values(array_map('intval', (array) $choice->final_choice_codes));
        $excluded = $finalChoices->activeExclusions()->get($registrationId, collect())->map(fn ($v)=>(int)$v)->all();
        $version = $circular->storedFinalizedSummary()['version'];
        $entries = CircularEntry::query()->where('version', $version)->whereIn('effective_code', $baseCodes)->get()->keyBy('effective_code');
        $history = ManualAllocationChoiceAdjustmentEvent::query()->where('registration_id', $registrationId)->orderByDesc('id')->get();
        $choiceCodeAbbrMap = $this->choiceCodeAbbrMap();

        return view('choice-optimization.manual-adjustment.show', compact('registration','choice','baseCodes','excluded','entries','history','choiceCodeAbbrMap'));
    }

    public function finalIndex(
        Request $request,
        FinalAllocationReadyChoiceService $finalChoices,
    ): View {
        $search = trim((string) $request->query('search', ''));
        $status = strtolower(trim((string) $request->query('status', 'all')));
        if (! in_array($status, ['all', 'adjusted', 'unchanged'], true)) $status = 'all';

        $activeExclusions = $finalChoices->activeExclusionEvents();
        $activeAdjustedIds = $activeExclusions->keys()->map(fn ($id) => (int) $id)->all();

        $query = ChoiceOptimizationHistoricalChoice::query()
            ->join('registrations as r', 'r.id', '=', 'choice_optimization_historical_choices.registration_id')
            ->select('choice_optimization_historical_choices.*', 'r.name as candidate_name');

        if ($search !== '') {
            $query->where(fn ($q) => $q->where('r.reg', 'like', '%'.$search.'%')->orWhere('r.name', 'like', '%'.$search.'%'));
        }
        if ($status === 'adjusted') {
            $query->whereIn('choice_optimization_historical_choices.registration_id', $activeAdjustedIds ?: [-1]);
        } elseif ($status === 'unchanged' && $activeAdjustedIds !== []) {
            $query->whereNotIn('choice_optimization_historical_choices.registration_id', $activeAdjustedIds);
        }

        $rows = $query->orderBy('r.reg')->paginate(100)->withQueryString();
        $effectiveMap = $finalChoices->effectiveMap();
        $choiceCodeAbbrMap = $this->choiceCodeAbbrMap();

        return view('choice-optimization.final-allocation-ready-choice.index', compact(
            'rows','search','status','activeExclusions','effectiveMap','choiceCodeAbbrMap'
        ));
    }

    public function finalShow(
        int $registrationId,
        FinalAllocationReadyChoiceService $finalChoices,
    ): View {
        $registration = Registration::query()->findOrFail($registrationId);
        $choice = ChoiceOptimizationHistoricalChoice::query()->where('registration_id', $registrationId)->firstOrFail();
        $baseCodes = array_values(array_map('intval', (array) $choice->final_choice_codes));
        $manualEvents = $finalChoices->activeExclusionEvents(collect([$registrationId]))->get($registrationId, collect());
        $excludedCodes = $manualEvents->pluck('choice_code')->map(fn ($v) => (int) $v)->all();
        $finalCodes = array_values(array_filter($baseCodes, fn ($code) => ! in_array((int) $code, $excludedCodes, true)));
        $choiceCodeAbbrMap = $this->choiceCodeAbbrMap();

        return view('choice-optimization.final-allocation-ready-choice.show', compact(
            'registration','choice','baseCodes','manualEvents','excludedCodes','finalCodes','choiceCodeAbbrMap'
        ));
    }

    public function exclude(Request $request, int $registrationId, int $choiceCode, ManualAllocationChoiceAdjustmentService $service): RedirectResponse
    {
        $validated = $request->validate(['reason'=>['required','string','min:3','max:5000']]);
        $service->exclude($registrationId, $choiceCode, $validated['reason'], $request->user()?->id);
        return redirect()->route('choice-optimization.manual-adjustment.show', $registrationId)
            ->with('success', 'Choice excluded from Final Allocation Ready Choice. Current Allocation lineage is stale; re-freeze A2 and re-run/re-finalize Allocation.');
    }

    public function restore(Request $request, int $registrationId, int $choiceCode, ManualAllocationChoiceAdjustmentService $service): RedirectResponse
    {
        $validated = $request->validate(['reason'=>['required','string','min:3','max:5000']]);
        $service->restore($registrationId, $choiceCode, $validated['reason'], $request->user()?->id);
        return redirect()->route('choice-optimization.manual-adjustment.show', $registrationId)
            ->with('success', 'Choice restored to Final Allocation Ready Choice. Current Allocation lineage is stale; re-freeze A2 and re-run/re-finalize Allocation.');
    }

    /** @return array<int,string> */
    private function choiceCodeAbbrMap(): array
    {
        $map = [];
        foreach (CadreMaster::query()->get(['cadre_code','cadre_abbr']) as $cadre) {
            $code = (int) $cadre->cadre_code;
            $abbr = trim((string) $cadre->cadre_abbr);
            if ($code > 0 && $abbr !== '') $map[$code][] = $abbr;
        }
        foreach (CadreSubMaster::query()->get(['sub_cadre_code','sub_cadre_abbr']) as $subCadre) {
            $code = (int) $subCadre->sub_cadre_code;
            $abbr = trim((string) $subCadre->sub_cadre_abbr);
            if ($code > 0 && $abbr !== '') $map[$code][] = $abbr;
        }

        return collect($map)->map(fn (array $abbrs): string => collect($abbrs)->filter()->unique()->implode(' / '))->all();
    }
}
