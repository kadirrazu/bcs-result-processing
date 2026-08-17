<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessMeritGeneration;
use App\Models\MeritCadreRank;
use App\Models\MeritFinalizationRun;
use App\Models\MeritProcessingAudit;
use App\Models\MeritProcessingRun;
use App\Models\MeritProcessingState;
use App\Models\MeritResult;
use App\Models\ChoiceValidationResult;
use App\Models\CadreMaster;
use App\Models\CadreSubMaster;
use App\Models\BachelorSubject;
use App\Models\PostRelatedSubject;
use App\Models\PreliminaryResult;
use App\Models\Registration;
use App\Models\TabulationResult;
use App\Models\VivaResult;
use App\Models\WrittenResult;
use App\Models\User;
use App\Services\Exports\AdministrativeXlsxExportService;
use App\Services\Merit\MeritFinalizationService;
use App\Services\Merit\MeritRollbackService;
use App\Services\Merit\MeritReadinessService;
use App\Services\Merit\MeritReviewSummaryService;
use App\Services\Merit\MeritRunService;
use App\Services\Merit\MeritStaleService;
use App\Reports\Pdf\MeritIndividualPdfReport;
use App\Support\Examinations\ExaminationContext;
use App\Support\Registrations\RegistrationReferencePresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

final class MeritController extends Controller
{
    public function index(MeritReadinessService $readiness, MeritStaleService $stale): View
    {
        $this->authorize('viewAny', MeritResult::class);
        $readinessInspection = $readiness->inspect();
        $state = $stale->synchronize($readinessInspection);
        $audits = MeritProcessingAudit::query()->latest('id')->limit(10)->get();
        $finalizationHistory = MeritFinalizationRun::query()->latest('id')->limit(10)->get();
        $actorIds = $audits->pluck('actor_id')
            ->merge($finalizationHistory->pluck('finalized_by'))
            ->filter()
            ->unique()
            ->values();
        $actors = User::query()->whereIn('id', $actorIds)->get(['id', 'name', 'email'])->keyBy('id');

        return view('merit.index', [
            'state' => $state,
            'readiness' => $readinessInspection,
            'latestRun' => MeritProcessingRun::query()->latest('id')->first(),
            'latestFinalization' => MeritFinalizationRun::query()->latest('id')->first(),
            'finalizationHistory' => $finalizationHistory,
            'audits' => $audits,
            'auditActors' => $actors,
        ]);
    }

    public function start(Request $request, MeritRunService $service, ExaminationContext $context): RedirectResponse
    {
        $this->authorize('process', MeritResult::class);
        $examId = $context->currentId();
        abort_if($examId === null, 409, 'No examination selected.');
        $run = $service->create($request->user());
        ProcessMeritGeneration::dispatch($examId, $run->id);
        return redirect()->route('merit.run.show', $run)->with('success', 'Merit Generation queued.');
    }

    public function runShow(MeritProcessingRun $run): View
    {
        $this->authorize('viewAny', MeritResult::class);
        return view('merit.run', compact('run'));
    }

    public function runStatus(MeritProcessingRun $run): JsonResponse
    {
        $this->authorize('viewAny', MeritResult::class);
        $run->refresh();
        return response()->json([
            'status' => $run->status,
            'total_rows' => (int) $run->total_rows,
            'processed_rows' => (int) $run->processed_rows,
            'common_ranked_count' => (int) $run->common_ranked_count,
            'general_ranked_count' => (int) $run->general_ranked_count,
            'technical_ranked_count' => (int) $run->technical_ranked_count,
            'cadre_rank_rows' => (int) $run->cadre_rank_rows,
            'progress_percent' => (float) $run->progress_percent,
            'current_step' => $run->current_step,
            'failure_message' => $run->failure_message,
            'finished' => ! in_array($run->status, ['queued', 'running'], true),
        ]);
    }

    public function results(Request $request, MeritStaleService $stale, MeritReviewSummaryService $summary): View
    {
        $this->authorize('viewAny', MeritResult::class);
        $state = $stale->synchronize();
        $runId = $request->integer('run') ?: $state?->latest_run_id;
        abort_if(! $runId, 404, 'No Merit run available.');
        $run = MeritProcessingRun::query()->findOrFail($runId);

        $search = trim((string) $request->query('search', ''));
        $track = strtoupper(trim((string) $request->query('track', '')));
        $scope = trim((string) $request->query('scope', ''));

        $q = MeritResult::query()
            ->leftJoin('registrations as registration_lookup', 'registration_lookup.id', '=', 'merit_results.registration_id')
            ->leftJoin('tabulation_results as tabulation_lookup', 'tabulation_lookup.id', '=', 'merit_results.tabulation_result_id')
            ->where('merit_results.processing_run_id', $runId)
            ->select(
                'merit_results.*',
                'registration_lookup.name as candidate_name',
                'tabulation_lookup.general_grand_total as general_grand_total',
                'tabulation_lookup.technical_grand_total as technical_grand_total',
            );

        if ($search !== '') {
            $q->where(function ($x) use ($search): void {
                $x->where('merit_results.reg', 'like', "%{$search}%")
                    ->orWhere('merit_results.user_id', 'like', "%{$search}%")
                    ->orWhere('registration_lookup.name', 'like', "%{$search}%");
            });
        }

        if (in_array($track, ['GG', 'GN', 'TT', 'T', 'GT'], true)) {
            $q->where('merit_results.written_qualified_track', $track);
        }

        match ($scope) {
            'common' => $q->whereNotNull('merit_results.common_merit_position'),
            'general' => $q->whereNotNull('merit_results.general_merit_position'),
            'technical' => $q->whereNotNull('merit_results.technical_merit_position'),
            'not_eligible' => $q->where('merit_results.status_reason', 'NOT_MERIT_ELIGIBLE'),
            default => null,
        };

        $rows = $q->orderByRaw('merit_results.common_merit_position IS NULL')
            ->orderBy('merit_results.common_merit_position')
            ->orderBy('merit_results.reg')
            ->paginate((int) config('merit.page_size', 100))
            ->withQueryString();

        $cadres = MeritCadreRank::query()
            ->where('processing_run_id', $runId)
            ->selectRaw('cadre_code,cadre_abbr,cadre_type,COUNT(*) as candidate_count')
            ->groupBy('cadre_code', 'cadre_abbr', 'cadre_type')
            ->orderBy('cadre_code')
            ->get();

        return view('merit.results', [
            'state' => $state,
            'run' => $run,
            'rows' => $rows,
            'cadres' => $cadres,
            'search' => $search,
            'track' => $track,
            'scope' => $scope,
            'reviewSummary' => $summary->forRun($run),
            'latestFinalization' => MeritFinalizationRun::query()->where('processing_run_id', $runId)->latest('id')->first(),
        ]);
    }

    public function show(MeritResult $result): View
    {
        $this->authorize('view', $result);
        $state = MeritProcessingState::query()->first();

        $result->load([
            'run',
            'cadreRanks' => fn ($q) => $q->orderBy('cadre_code'),
        ]);

        abort_unless(
            $state?->latest_run_id === $result->processing_run_id
            && ! $state?->is_stale
            && $result->run?->status === 'completed'
            && in_array((string) $state?->status, ['review_ready', 'finalized'], true),
            409,
            'Individual Merit View is available only for the current completed Merit run.'
        );

        $tabulation = TabulationResult::query()->findOrFail($result->tabulation_result_id);
        $registration = Registration::query()->findOrFail($tabulation->registration_id);
        $bachelorSubjectTitle = filled($registration->bachelor_subject_code)
            ? BachelorSubject::query()->where('subject_code', $registration->bachelor_subject_code)->value('subject_name')
            : null;
        $postRelatedSubjectTitle = filled($registration->post_related_subject_code)
            ? PostRelatedSubject::query()->where('subject_code', $registration->post_related_subject_code)->value('subject_name')
            : null;
        $bachelorSubjectDisplay = RegistrationReferencePresenter::codeTitle(
            $registration->bachelor_subject_code,
            $bachelorSubjectTitle,
            'Unmapped bachelor subject code',
        );
        $postRelatedSubjectDisplay = RegistrationReferencePresenter::codeTitle(
            $registration->post_related_subject_code,
            $postRelatedSubjectTitle,
            'Unmapped post-related subject code',
        );
        $preliminary = $tabulation->preliminary_result_id ? PreliminaryResult::query()->find($tabulation->preliminary_result_id) : null;
        $written = WrittenResult::query()->findOrFail($tabulation->written_result_id);
        $viva = VivaResult::query()->findOrFail($tabulation->viva_result_id);

        $choiceVersion = (int) data_get($result->run?->source_snapshot, 'choice_validation.validation_version', 0);
        $circularVersion = (int) data_get($result->run?->source_snapshot, 'circular.version', 0);
        $choiceValidation = $choiceVersion > 0
            ? ChoiceValidationResult::query()
                ->with(['source.items' => fn ($q) => $q->orderBy('position')])
                ->where('registration_id', $result->registration_id)
                ->where('validation_version', $choiceVersion)
                ->when($circularVersion > 0, fn ($q) => $q->where('circular_version', $circularVersion))
                ->first()
            : null;

        $originalChoiceCodes = $choiceValidation?->source?->items
            ?->filter(fn ($item) => filled($item->choice_code ?? $item->raw_value))
            ->map(fn ($item) => (string) ($item->choice_code ?? $item->raw_value))
            ->values()
            ->all() ?? [];
        $validatedChoiceCodes = array_values(array_map('strval', (array) ($choiceValidation?->validated_choice_codes ?? [])));

        $choiceCodes = array_values(array_unique(array_filter(array_merge($originalChoiceCodes, $validatedChoiceCodes), fn ($code) => $code !== '')));
        $mainCadres = CadreMaster::query()
            ->whereIn('cadre_code', array_map('intval', $choiceCodes))
            ->get(['cadre_code', 'cadre_abbr'])
            ->mapWithKeys(fn ($row) => [(string) $row->cadre_code => (string) $row->cadre_abbr]);
        $subCadres = CadreSubMaster::query()
            ->whereIn('sub_cadre_code', array_map('intval', $choiceCodes))
            ->get(['sub_cadre_code', 'sub_cadre_abbr'])
            ->mapWithKeys(fn ($row) => [(string) $row->sub_cadre_code => (string) $row->sub_cadre_abbr]);
        $abbrFor = static fn (string $code): string => (string) ($mainCadres->get($code) ?? $subCadres->get($code) ?? 'UNKNOWN');

        $originalChoiceAbbrs = array_map($abbrFor, $originalChoiceCodes);
        $validatedChoiceAbbrs = array_map($abbrFor, $validatedChoiceCodes);

        return view('merit.show', compact(
            'result',
            'registration',
            'bachelorSubjectDisplay',
            'postRelatedSubjectDisplay',
            'preliminary',
            'written',
            'viva',
            'tabulation',
            'choiceValidation',
            'originalChoiceCodes',
            'originalChoiceAbbrs',
            'validatedChoiceCodes',
            'validatedChoiceAbbrs',
            'state',
        ));
    }

    public function cadre(Request $request, int $cadreCode): View
    {
        $this->authorize('viewAny', MeritResult::class);
        $state = MeritProcessingState::query()->first();
        $runId = $request->integer('run') ?: $state?->latest_run_id;
        abort_if(! $runId, 404);

        $search = trim((string) $request->query('search', ''));

        $rowsQuery = MeritCadreRank::query()
            ->where('merit_cadre_ranks.processing_run_id', $runId)
            ->where('merit_cadre_ranks.cadre_code', $cadreCode)
            ->join('merit_results as m', 'm.id', '=', 'merit_cadre_ranks.merit_result_id')
            ->leftJoin('registrations as r', 'r.id', '=', 'merit_cadre_ranks.registration_id')
            ->select('merit_cadre_ranks.*', 'm.id as merit_result_id', 'm.reg', 'm.user_id', 'm.common_merit_position', 'm.general_merit_position', 'm.technical_merit_position', 'm.all_merit_tech', 'r.name as candidate_name');

        if ($search !== '') {
            $rowsQuery->where(function ($q) use ($search): void {
                $q->where('m.reg', 'like', "%{$search}%")
                    ->orWhere('m.user_id', 'like', "%{$search}%")
                    ->orWhere('r.name', 'like', "%{$search}%");
            });
        }

        $rows = $rowsQuery
            ->orderBy('merit_cadre_ranks.cadre_merit_position')
            ->paginate(100)
            ->withQueryString();

        $meta = MeritCadreRank::query()->where('processing_run_id', $runId)->where('cadre_code', $cadreCode)->firstOrFail();
        return view('merit.cadre', compact('rows', 'meta', 'runId', 'state', 'search'));
    }

    public function exportAll(AdministrativeXlsxExportService $exporter): BinaryFileResponse
    {
        $this->authorize('viewAny', MeritResult::class);
        [$state, $run] = $this->currentFinalizedRun();
        $dir = storage_path('app/private/merit');
        File::ensureDirectoryExists($dir);
        $path = $dir.'/merit-final-v'.$run->processing_version.'-'.now()->format('Ymd-His').'.xlsx';

        $rows = (function () use ($run) {
            $q = DB::connection('exam')->table('merit_results as m')
                ->leftJoin('registrations as r', 'r.id', '=', 'm.registration_id')
                ->where('m.processing_run_id', $run->id)
                ->orderByRaw('m.common_merit_position IS NULL')
                ->orderBy('m.common_merit_position')
                ->orderBy('m.reg')
                ->select('m.*', 'r.name');
            foreach ($q->cursor() as $row) {
                yield [
                    $row->reg, $row->user_id, $row->name, $row->cadre_category, $row->written_qualified_track,
                    $row->common_merit_position, $row->general_merit_position, $row->technical_merit_position,
                    MeritResult::allMeritTechJson($row->all_merit_tech), $row->status_reason ?: 'MERIT_RANKED', $row->processing_version,
                ];
            }
        })();

        $exporter->create($path, [
            'Processing Version' => $run->processing_version,
            'Dataset Hash' => $state->dataset_hash,
            'Total Rows' => $run->total_rows,
            'Common Ranked' => $run->common_ranked_count,
            'General Ranked' => $run->general_ranked_count,
            'Technical Ranked' => $run->technical_ranked_count,
            'Cadre-wise Rank Rows' => $run->cadre_rank_rows,
        ], ['REG', 'USER', 'Name', 'Registration Category', 'Written Qualified Track', 'Common Merit', 'General Merit', 'Technical Merit', 'all_merit_tech', 'Status', 'Version'], $rows);

        return response()->download($path, basename($path))->deleteFileAfterSend();
    }

    public function exportCadre(int $cadreCode, AdministrativeXlsxExportService $exporter): BinaryFileResponse
    {
        $this->authorize('viewAny', MeritResult::class);
        [$state, $run] = $this->currentFinalizedRun();
        $meta = MeritCadreRank::query()->where('processing_run_id', $run->id)->where('cadre_code', $cadreCode)->firstOrFail();
        $dir = storage_path('app/private/merit');
        File::ensureDirectoryExists($dir);
        $path = $dir.'/merit-cadre-'.$cadreCode.'-v'.$run->processing_version.'-'.now()->format('Ymd-His').'.xlsx';

        $rows = (function () use ($run, $cadreCode) {
            $q = DB::connection('exam')->table('merit_cadre_ranks as c')
                ->join('merit_results as m', 'm.id', '=', 'c.merit_result_id')
                ->leftJoin('registrations as r', 'r.id', '=', 'c.registration_id')
                ->where('c.processing_run_id', $run->id)
                ->where('c.cadre_code', $cadreCode)
                ->orderBy('c.cadre_merit_position')
                ->select('c.*', 'm.reg', 'm.user_id', 'm.common_merit_position', 'm.general_merit_position', 'm.technical_merit_position', 'm.all_merit_tech', 'r.name');
            foreach ($q->cursor() as $row) {
                yield [
                    $row->cadre_merit_position, $row->reg, $row->user_id, $row->name,
                    $row->source_merit_position, $row->choice_position,
                    $row->common_merit_position, $row->general_merit_position, $row->technical_merit_position,
                    MeritResult::allMeritTechJson($row->all_merit_tech),
                ];
            }
        })();

        $count = MeritCadreRank::query()->where('processing_run_id', $run->id)->where('cadre_code', $cadreCode)->count();
        $exporter->create($path, [
            'Cadre' => $meta->cadre_code.' ('.$meta->cadre_abbr.')',
            'Cadre Type' => $meta->cadre_type,
            'Candidates' => $count,
            'Processing Version' => $run->processing_version,
            'Dataset Hash' => $state->dataset_hash,
        ], ['Cadre Merit', 'REG', 'USER', 'Name', 'Source Merit', 'Choice Position', 'Common Merit', 'General Merit', 'Technical Merit', 'all_merit_tech'], $rows);

        return response()->download($path, basename($path))->deleteFileAfterSend();
    }

    public function pdf(MeritResult $result, MeritIndividualPdfReport $report): Response
    {
        $this->authorize('view', $result);
        $state = MeritProcessingState::query()->first();

        abort_unless(
            $state?->status === 'finalized'
            && ! $state?->is_stale
            && $state?->latest_run_id === $result->processing_run_id,
            409,
            'Individual Merit PDF is available only for the current finalized Merit run.'
        );

        $pdf = $report->generate($result);

        return response($pdf['content'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$pdf['filename'].'"',
        ]);
    }

    public function rollback(
        Request $request,
        MeritFinalizationRun $finalization,
        MeritRollbackService $service,
    ): RedirectResponse {
        $this->authorize('process', MeritResult::class);

        $data = $request->validate([
            'confirmation' => ['required', 'string'],
            'reason' => ['nullable', 'string', 'max:4000'],
        ]);

        $service->rollback(
            $finalization,
            $request->user(),
            $data['confirmation'],
            $data['reason'] ?? null,
        );

        return redirect()->route('merit.results', ['run' => $finalization->processing_run_id])
            ->with('success', 'Compatible historical Merit version restored.');
    }

    public function finalize(Request $request, MeritFinalizationService $service): RedirectResponse
    {
        $this->authorize('process', MeritResult::class);
        $data = $request->validate(['confirmation' => ['required', 'string'], 'notes' => ['nullable', 'string', 'max:4000']]);
        $service->finalize($request->user(), $data['confirmation'], $data['notes'] ?? null);
        return redirect()->route('merit.results')->with('success', 'Merit Generation finalized successfully.');
    }

    /** @return array{0:MeritProcessingState,1:MeritProcessingRun} */
    private function currentFinalizedRun(): array
    {
        $state = MeritProcessingState::query()->first();
        abort_unless($state?->status === 'finalized' && ! $state?->is_stale && $state?->latest_run_id, 409, 'Finalize the current Merit Generation before export.');
        return [$state, MeritProcessingRun::query()->findOrFail($state->latest_run_id)];
    }
}
