<?php

namespace App\Http\Controllers\NonCadre\Circular;

use App\Http\Controllers\Controller;
use App\Services\NonCadre\Circular\NonCadreCircularSpreadsheetService;
use App\Services\NonCadre\Circular\NonCadreCircularWorkflowService;
use App\Services\NonCadre\NonCadreReadinessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Reports\Pdf\NonCadre\NonCadreCircularPdfReport;
use App\Services\NonCadre\Circular\NonCadreCircularExcelExportService;

final class NonCadreCircularController extends Controller
{
    public function index(NonCadreReadinessService $readiness): View
    {
        $gate = $readiness->inspect();
        $versions = DB::connection('exam')->table('non_cadre_circular_versions')->orderByDesc('version')->get();
        $latestImport = DB::connection('exam')->table('non_cadre_circular_imports')->orderByDesc('id')->first();
        $effective = $versions->firstWhere('status', 'finalized');
        $effectiveGradeCounts = collect();

        if ($effective) {
            $effectiveGradeCounts = DB::connection('exam')->table('non_cadre_circular_posts')
                ->where('circular_version_id', $effective->id)
                ->selectRaw('post_grade, SUM(post_count) as total_post_count')
                ->groupBy('post_grade')
                ->orderByRaw('post_grade IS NULL ASC')
                ->orderBy('post_grade')
                ->get()
                ->mapWithKeys(static fn ($row) => [
                    $row->post_grade === null ? 'Ungraded' : (string) $row->post_grade => (int) $row->total_post_count,
                ]);
        }

        return view('non-cadre.circular.index', compact('gate','versions','latestImport','effective','effectiveGradeCounts'));
    }

    public function template(NonCadreCircularSpreadsheetService $service): BinaryFileResponse { return $service->template(); }

    public function upload(Request $request, NonCadreReadinessService $readiness, NonCadreCircularSpreadsheetService $service): RedirectResponse
    {
        $readiness->requireReady();
        $request->validate(['file' => ['required','file','mimes:xlsx,xls','max:20480']]);
        try { $id = $service->stage($request->file('file'), $request->user()?->id); }
        catch (\Throwable $e) { report($e); return back()->withErrors(['file' => $e->getMessage()]); }
        return redirect()->route('non-cadre.circular.import.review', $id);
    }

    public function review(int $import): View
    {
        $batch = DB::connection('exam')->table('non_cadre_circular_imports')->find($import); abort_if(! $batch, 404);
        $rows = DB::connection('exam')->table('non_cadre_circular_import_rows')->where('import_id', $import)->orderBy('row_number')->paginate(100);
        return view('non-cadre.circular.review', compact('batch','rows'));
    }

    public function approve(int $import, Request $request, NonCadreCircularWorkflowService $workflow): RedirectResponse
    {
        try { $versionId = $workflow->approveImport($import, $request->user()?->id); }
        catch (\Throwable $e) { if ($e instanceof \Illuminate\Validation\ValidationException) throw $e; report($e); return back()->withErrors(['import' => $e->getMessage()]); }
        return redirect()->route('non-cadre.circular.version', $versionId)->with('success', 'Non-Cadre Circular import approved as a new draft version. Review and finalize it to make it effective.');
    }

    public function version(int $version): View
    {
        $record = DB::connection('exam')->table('non_cadre_circular_versions')->find($version); abort_if(! $record, 404);
        $posts = DB::connection('exam')->table('non_cadre_circular_posts')
            ->where('circular_version_id', $version)
            ->orderByRaw('post_grade IS NULL ASC')
            ->orderBy('post_grade')
            ->orderBy('post_serial')
            ->orderByRaw('post_sub_serial IS NULL DESC')
            ->orderBy('post_sub_serial')
            ->get();
        $gradeGroups = $posts->groupBy(fn ($post) => $post->post_grade === null ? 'Ungraded' : (string) $post->post_grade);
        $gradeCounts = $gradeGroups->map(fn ($group) => (int) $group->sum('post_count'));
        return view('non-cadre.circular.version', compact('record','posts','gradeGroups','gradeCounts'));
    }

    public function pdf(int $version, NonCadreCircularPdfReport $report): StreamedResponse
    {
        $file = $report->generate($version);
        return response()->streamDownload(static function () use ($file): void { echo $file['content']; }, $file['filename'], ['Content-Type' => 'application/pdf']);
    }

    public function excel(int $version, NonCadreCircularExcelExportService $report): StreamedResponse
    {
        $file = $report->generate($version);
        return response()->streamDownload(static function () use ($file): void { echo $file['content']; }, $file['filename'], ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    public function finalize(int $version, Request $request, NonCadreCircularWorkflowService $workflow): RedirectResponse
    {
        $workflow->finalize($version, $request->user()?->id);
        return redirect()->route('non-cadre.circular.index')->with('success', 'Non-Cadre Circular finalized and is now the effective version.');
    }
}
