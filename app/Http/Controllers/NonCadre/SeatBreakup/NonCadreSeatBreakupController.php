<?php
namespace App\Http\Controllers\NonCadre\SeatBreakup;
use App\Http\Controllers\Controller;
use App\Services\NonCadre\NonCadreReadinessService;
use App\Services\NonCadre\SeatBreakup\NonCadreSeatBreakupService;
use Illuminate\Http\RedirectResponse; use Illuminate\Http\Request; use Illuminate\Support\Facades\DB; use Illuminate\View\View; use Symfony\Component\HttpFoundation\BinaryFileResponse; use Symfony\Component\HttpFoundation\StreamedResponse; use App\Reports\Pdf\NonCadre\NonCadreSeatBreakupPdfReport;
final class NonCadreSeatBreakupController extends Controller
{
 public function index(NonCadreReadinessService $readiness,NonCadreSeatBreakupService $service):View
 { $gate=$readiness->inspect(); $circular=null; try{$circular=$service->effectiveCircular();}catch(\Throwable){} $versions=DB::connection('exam')->table('non_cadre_seat_breakup_versions')->orderByDesc('version')->get(); $totals=[]; foreach($versions as $v)$totals[$v->id]=$service->totals((int)$v->id); $quota=(array)config('allocation.provisional_breakup_percentages',[]); $minimum=(int)config('allocation.quota_breakup_minimum_total_posts',10); return view('non-cadre.seat-breakup.index',compact('gate','circular','versions','totals','quota','minimum')); }
 public function template(NonCadreSeatBreakupService $service):BinaryFileResponse { $path=$service->templatePath(); return response()->download($path,'non-cadre-seat-breakup-'.now()->format('Ymd-His').'.xlsx')->deleteFileAfterSend(true); }
 public function upload(Request $request,NonCadreSeatBreakupService $service):RedirectResponse { $request->validate(['file'=>['required','file','mimes:xlsx,xls','max:20480']]); $v=$service->import($request->file('file'),$request->user()?->id); return redirect()->route('non-cadre.seat-breakup.version',$v->id)->with('success',"Non-Cadre Seat Breakup v{$v->version} validated. Review and finalize it."); }
 public function version(int $version,NonCadreSeatBreakupService $service):View { $record=DB::connection('exam')->table('non_cadre_seat_breakup_versions')->find($version); abort_if(!$record,404); $rows=$service->orderedRows($version); $groups=$rows->groupBy(fn($r)=>$r->post_grade===null?'Ungraded':(string)$r->post_grade); $totals=$service->totals($version); return view('non-cadre.seat-breakup.version',compact('record','rows','groups','totals')); }
 public function pdf(int $version,NonCadreSeatBreakupPdfReport $report):StreamedResponse { $file=$report->generate($version); return response()->streamDownload(static function () use ($file): void { echo $file['content']; },$file['filename'],['Content-Type'=>'application/pdf']); }
 public function finalize(int $version,Request $request,NonCadreSeatBreakupService $service):RedirectResponse { $v=$service->finalize($version,$request->user()?->id); return redirect()->route('non-cadre.seat-breakup.version',$v->id)->with('success',"Non-Cadre Seat Breakup v{$v->version} finalized/frozen."); }
}
