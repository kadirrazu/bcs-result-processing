<?php

namespace App\Http\Controllers;

use App\Models\AllocationA4Result;
use App\Models\AllocationA6ExportAudit;
use App\Models\Registration;
use App\Services\Allocation\AllocationA6ExportService;
use App\Services\Allocation\AllocationA6ReadinessService;
use App\Services\Allocation\AllocationA6ReportService;
use App\Services\Documents\DocxPlaceholderTemplateService;
use App\Support\Examinations\ExaminationContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class AllocationA6Controller extends Controller
{
    public function index(AllocationA6ReadinessService $readiness, AllocationA6ReportService $reports): View
    {
        $gate = $readiness->inspect();
        $cadres = $gate['ready'] ? $reports->cadres($gate['a5']) : collect();
        $audits = AllocationA6ExportAudit::query()->latest('id')->limit(10)->get();
        return view('allocation.a6.index', compact('gate','cadres','audits'));
    }

    public function candidates(Request $request, AllocationA6ReadinessService $readiness, AllocationA6ReportService $reports): View
    {
        $a5 = $readiness->requireReady();
        $search = trim((string)$request->query('search',''));
        $baseQuery = $reports->tabulationEligibleQuery();
        $totalCandidates = (clone $baseQuery)->count();
        $query = $baseQuery
            ->join('registrations as r','r.id','=','tabulation_results.registration_id')
            ->select('tabulation_results.*','r.name as candidate_name','r.user_id as registration_user_id');
        if ($search !== '') {
            $query->where(function($q) use ($search): void {
                $q->where('r.reg','like','%'.$search.'%')->orWhere('r.user_id','like','%'.$search.'%')->orWhere('r.name','like','%'.$search.'%');
            });
        }
        $results = $query->orderBy('r.reg')->paginate(100)->withQueryString();
        $allocatedRegs = AllocationA4Result::query()->where('allocation_a4_run_id',(int)$a5->allocation_a4_run_id)->whereIn('reg',$results->pluck('reg'))->get()->keyBy('reg');
        $allocationAbbr = $reports->abbreviations($allocatedRegs->pluck('cadre_code'));
        return view('allocation.a6.candidates', compact('a5','results','search','allocatedRegs','allocationAbbr','totalCandidates'));
    }

    public function candidate(string $reg, AllocationA6ReadinessService $readiness, AllocationA6ReportService $reports): View
    {
        $a5 = $readiness->requireReady();
        $data = $reports->candidateDetail($reg, $a5);
        return view('allocation.a6.candidate-show', compact('a5','data'));
    }

    public function cadres(AllocationA6ReadinessService $readiness, AllocationA6ReportService $reports): View
    {
        $a5 = $readiness->requireReady();
        $cadres = $reports->cadres($a5);
        return view('allocation.a6.cadres', compact('a5','cadres'));
    }

    public function cadre(int $cadreCode, AllocationA6ReadinessService $readiness, AllocationA6ReportService $reports): View
    {
        $a5 = $readiness->requireReady();
        $cadre = $reports->cadres($a5)->firstWhere('code',$cadreCode);
        abort_if($cadre === null, 404);
        $results = AllocationA4Result::query()->where('allocation_a4_run_id',(int)$a5->allocation_a4_run_id)->where('cadre_code',$cadreCode)
            ->orderBy('merit_position')->orderBy('reg')->paginate(100);
        $names = Registration::query()->whereIn('id',$results->pluck('registration_id'))->pluck('name','id');
        return view('allocation.a6.cadre-show', compact('a5','cadre','results','names'));
    }

    public function exportTxt(Request $request, AllocationA6ReadinessService $readiness, AllocationA6ExportService $exports): BinaryFileResponse
    {
        $a5 = $readiness->requireReadyStrict();
        $v = $request->validate([
            'mode'=>['required','in:consolidated,cadre_zip'], 'registrations_per_line'=>['required','integer','min:1','max:20'],
            'report_title'=>['nullable','string','max:150'],
        ]);
        $title = trim((string)($v['report_title'] ?? 'Final Cadre Allocation')) ?: 'Final Cadre Allocation';
        [$path,$name] = $v['mode']==='cadre_zip'
            ? $exports->cadreTxtZip($a5,(int)$v['registrations_per_line'],$title)
            : $exports->consolidatedTxt($a5,(int)$v['registrations_per_line'],$title);
        $exports->audit($a5,'TXT',(string)$v['mode'],null,['registrations_per_line'=>(int)$v['registrations_per_line'],'report_title'=>$title],$path,$name,$request->user()?->id);
        return response()->download($path,$name,['Content-Type'=>$v['mode']==='cadre_zip'?'application/zip':'text/plain; charset=UTF-8'])->deleteFileAfterSend(true);
    }

    public function exportXlsx(Request $request, AllocationA6ReadinessService $readiness, AllocationA6ExportService $exports): BinaryFileResponse
    {
        $a5 = $readiness->requireReadyStrict();
        $v = $request->validate(['scope'=>['required','in:tabulation_eligible,allocated,cadre'],'cadre_code'=>['nullable','integer']]);
        $cadre = $v['scope']==='cadre' ? (int)($v['cadre_code'] ?? 0) : null;
        if ($v['scope']==='cadre' && $cadre < 1) abort(422,'Cadre code is required.');
        [$path,$name] = $exports->xlsx($a5,(string)$v['scope'],$cadre);
        $exports->audit($a5,'XLSX',(string)$v['scope'],$cadre,[],$path,$name,$request->user()?->id);
        return response()->download($path,$name,['Content-Type'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->deleteFileAfterSend(true);
    }

    public function docx(AllocationA6ReadinessService $readiness, ExaminationContext $context): View
    {
        $a5 = $readiness->requireReady();
        return view('allocation.a6.docx', ['a5'=>$a5,'examination'=>$context->current(),'defaultPerLine'=>10]);
    }

    public function generateDocx(Request $request, AllocationA6ReadinessService $readiness, AllocationA6ExportService $exports, DocxPlaceholderTemplateService $documents): BinaryFileResponse
    {
        $a5 = $readiness->requireReadyStrict();
        $v = $request->validate([
            'template_file'=>['required','file','mimes:docx','max:20480'],
            'result_date'=>['required','date'],
            'registrations_per_line'=>['required','integer','min:1','max:20'],
        ]);
        [$path,$name,$summary] = $exports->docx($a5,$request->file('template_file')->getRealPath(),date('d-m-Y',strtotime((string)$v['result_date'])),(int)$v['registrations_per_line'],$documents);
        $exports->audit($a5,'DOCX','template',null,[
            'template_name'=>$request->file('template_file')->getClientOriginalName(),'result_date'=>$v['result_date'],
            'registrations_per_line'=>(int)$v['registrations_per_line'],'replacements'=>$summary['total_replacements'] ?? 0,
            'unknown_placeholders'=>$summary['unknown_placeholders'] ?? [],
        ],$path,$name,$request->user()?->id);
        return response()->download($path,$name,['Content-Type'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document'])->deleteFileAfterSend(true);
    }
}
