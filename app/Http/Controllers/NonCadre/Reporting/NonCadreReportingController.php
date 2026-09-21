<?php

namespace App\Http\Controllers\NonCadre\Reporting;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessNonCadreReportingExport;
use App\Models\ReportingExportRun;
use App\Models\User;
use App\Services\Documents\DocxPlaceholderTemplateService;
use App\Services\NonCadre\Reporting\NonCadreDocxSampleTemplateService;
use App\Services\NonCadre\Reporting\NonCadreReportingService;
use App\Services\Reporting\ReportExportFileStore;
use App\Support\Examinations\ExaminationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class NonCadreReportingController extends Controller
{
    public function index(NonCadreReportingService $s): View
    {
        $gate = $s->gate();
        $posts = $gate['ready'] ? $s->posts() : collect();
        $exports = ReportingExportRun::query()
            ->where('module', 'non_cadre_reporting')
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        $operatorUsers = User::query()
            ->whereIn('id', $exports->pluck('generated_by')->filter()->unique()->values())
            ->get(['id', 'name'])
            ->keyBy('id');

        return view('non-cadre.reporting.index', compact('gate', 'posts', 'exports', 'operatorUsers'));
    }
    public function common(string $mode,NonCadreReportingService $s):View{$this->mode($mode);return view('non-cadre.reporting.common',['mode'=>$mode,'rows'=>$s->commonMerit($mode==='booklet'),'gate'=>$s->gate()]);}
    public function posts(string $mode,NonCadreReportingService $s):View{$this->mode($mode);return view('non-cadre.reporting.posts',['mode'=>$mode,'posts'=>$s->posts()]);}
    public function serialMerit(NonCadreReportingService $s,ExaminationContext $c):View{$data=$s->serialMeritReport();return view('non-cadre.reporting.serial-merit',[...$data,'examination'=>$c->current()]);}
    public function post(string $mode,string $postCode,NonCadreReportingService $s):View{$this->mode($mode);return view('non-cadre.reporting.post',['mode'=>$mode,'allocatedOnly'=>false,...$s->postReport($postCode,$mode==='booklet')]);}
    public function postAllocated(string $mode,string $postCode,NonCadreReportingService $s):View{$this->mode($mode);return view('non-cadre.reporting.post',['mode'=>$mode,'allocatedOnly'=>true,...$s->postAllocatedReport($postCode,$mode==='booklet')]);}
    public function docx(NonCadreReportingService $s,ExaminationContext $c):View{$s->requireReady();return view('non-cadre.reporting.docx',['examination'=>$c->current()]);}
    public function downloadDocxSample(NonCadreReportingService $s,NonCadreDocxSampleTemplateService $samples,ExaminationContext $c):BinaryFileResponse{$run=$s->requireReady();[$path,$name]=$samples->build($run,(string)($c->current()?->name??''));return response()->download($path,$name,['Content-Type'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document'])->deleteFileAfterSend(true);}
    public function queuePdf(Request $r,string $mode,NonCadreReportingService $s,ExaminationContext $c,?string $postCode=null):RedirectResponse{$this->mode($mode);$run=$s->requireReady();$scope=(string)$r->route('scope');$params=['mode'=>$mode];if($postCode!==null)$params['post_code']=$postCode;$x=$this->createRun($run,'PDF',$scope,$params,$r,$c);return redirect()->route('non-cadre.reporting.exports.show',$x)->with('success','Non-Cadre PDF export queued.');}
    public function candidateExport(Request $r,string $scope,string $format,NonCadreReportingService $s,ExaminationContext $c):RedirectResponse{$scope=strtolower($scope);$format=strtoupper($format);abort_unless(in_array($scope,['eligible','allocated'],true)&&in_array($format,['XLSX','DBF'],true),404);$run=$s->requireReady();$x=$this->createRun($run,$format,'candidate-'.$scope,['candidate_scope'=>$scope],$r,$c);return redirect()->route('non-cadre.reporting.exports.show',$x)->with('success','Non-Cadre candidate '.$format.' export queued.');}
    public function txt(Request $r,NonCadreReportingService $s,ExaminationContext $c):RedirectResponse{$run=$s->requireReady();$v=$r->validate(['registrations_per_line'=>['required','integer','min:1','max:20'],'report_title'=>['required','string','max:200']]);$x=$this->createRun($run,'TXT','consolidated',$v,$r,$c);return redirect()->route('non-cadre.reporting.exports.show',$x)->with('success','Non-Cadre TXT export queued.');}
    public function generateDocx(Request $r,NonCadreReportingService $s,ExaminationContext $c,ReportExportFileStore $files):RedirectResponse{$run=$s->requireReady();$v=$r->validate(['template_file'=>['required','file','mimes:docx','max:20480'],'result_date'=>['required','date'],'registrations_per_line'=>['required','integer','min:1','max:20']]);$x=$this->createRun($run,'DOCX','template',['result_date'=>date('d-m-Y',strtotime($v['result_date'])),'registrations_per_line'=>(int)$v['registrations_per_line'],'template_name'=>$r->file('template_file')->getClientOriginalName()],$r,$c,false);$p=(array)$x->parameters;$p['template_path']=$files->storeUploadedSource('non-cadre-reporting',$x->id,$r->file('template_file'));$x->forceFill(['parameters'=>$p])->save();$this->dispatch($x,$run,$c);return redirect()->route('non-cadre.reporting.exports.show',$x)->with('success','Non-Cadre DOCX generation queued.');}
    public function exportRun(ReportingExportRun $exportRun):View{$this->assertRun($exportRun);return view('non-cadre.reporting.export-show',['run'=>$exportRun]);}
    public function status(ReportingExportRun $exportRun):JsonResponse{$this->assertRun($exportRun);$exportRun->refresh();return response()->json(['status'=>$exportRun->status,'phase'=>$exportRun->phase,'progress_percent'=>(int)$exportRun->progress_percent,'progress_message'=>$exportRun->progress_message,'failure_message'=>$exportRun->failure_message,'finished'=>$exportRun->isFinished(),'download_url'=>$exportRun->status==='completed'?route('non-cadre.reporting.exports.download',$exportRun):null]);}
    public function download(ReportingExportRun $exportRun,NonCadreReportingService $s):BinaryFileResponse{$this->assertRun($exportRun);$current=$s->requireReady();$snap=(array)$exportRun->source_snapshot;abort_if((int)($snap['allocation_run_id']??0)!==(int)$current->id||!hash_equals((string)($snap['result_hash']??''),(string)$current->result_hash),409,'This NC5 export is OUTDATED. Regenerate it.');abort_unless($exportRun->status==='completed'&&$exportRun->file_path&&File::isFile($exportRun->file_path),404);return response()->download($exportRun->file_path,(string)$exportRun->file_name,['Content-Type'=>(string)$exportRun->file_mime]);}
    private function createRun(object $source,string $type,string $scope,array $parameters,Request $r,ExaminationContext $c,bool $dispatch=true):ReportingExportRun{$x=ReportingExportRun::query()->create(['module'=>'non_cadre_reporting','export_type'=>$type,'scope'=>$scope,'status'=>'queued','phase'=>'QUEUED','progress_percent'=>0,'progress_message'=>'Queued for generation.','parameters'=>$parameters,'source_snapshot'=>['allocation_run_id'=>(int)$source->id,'allocation_version'=>(int)$source->version,'result_hash'=>(string)$source->result_hash],'generated_by'=>$r->user()?->id,'queued_at'=>now()]);if($dispatch)$this->dispatch($x,$source,$c);return$x;}
    private function dispatch(ReportingExportRun $x,object $source,ExaminationContext $c):void{$exam=$c->current();abort_if(!$exam,409);ProcessNonCadreReportingExport::dispatch((int)$exam->id,(int)$x->id,(int)$source->id);}
    private function assertRun(ReportingExportRun $r):void{abort_unless($r->module==='non_cadre_reporting',404);}
    private function mode(string $m):void{abort_unless(in_array($m,['verification','booklet'],true),404);}
}
