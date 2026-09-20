<?php

namespace App\Http\Controllers\NonCadre\Choice;

use App\Http\Controllers\Controller;
use App\Services\NonCadre\Choice\NonCadreChoiceService;
use App\Support\Examinations\ExaminationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class NonCadreChoiceController extends Controller
{
    public function index(NonCadreChoiceService $service):View{$imports=DB::connection('exam')->table('non_cadre_choice_imports')->orderByDesc('version')->get();$summaries=[];foreach($imports as$i)$summaries[$i->id]=$service->summary((int)$i->id);return view('non-cadre.choice.index',['imports'=>$imports,'summaries'=>$summaries,'maxChoices'=>$service->maxChoices(),'headers'=>$service->headers()]);}
    public function template(NonCadreChoiceService $service):BinaryFileResponse{$path=$service->templatePath();return response()->download($path,'non-cadre-choice-template-'.$service->maxChoices().'-options.xlsx')->deleteFileAfterSend(true);}
    public function upload(Request$request,NonCadreChoiceService$service,ExaminationContext$context):RedirectResponse{$request->validate(['file'=>['required','file','mimes:xlsx,xls','max:20480']]);$exam=$context->current();abort_if(!$exam,409);$v=$service->enqueue($request->file('file'),$request->user()?->id,(int)$exam->id);return redirect()->route('non-cadre.choice.version',$v->id)->with('success',"NC3 Choice import v{$v->version} queued. You may leave this page while it processes.");}
    public function progress(int$version):JsonResponse{$r=DB::connection('exam')->table('non_cadre_choice_imports')->find($version);abort_if(!$r,404);return response()->json(['id'=>$r->id,'version'=>$r->version,'status'=>$r->status,'total_rows'=>(int)$r->total_rows,'processed_rows'=>(int)($r->processed_rows??0),'valid_rows'=>(int)$r->valid_rows,'invalid_rows'=>(int)$r->invalid_rows,'progress_percent'=>(int)($r->progress_percent??0),'failure_message'=>$r->failure_message??null]);}
    public function version(int$version,Request$request,NonCadreChoiceService$service):View{$record=DB::connection('exam')->table('non_cadre_choice_imports')->find($version);abort_if(!$record,404);$search=trim((string)$request->query('search',''));$filter=(string)$request->query('filter','all');$items=$service->paginatedItems($version,$search,$filter);$summary=$service->summary($version);$postMap=$service->postMap((int)$record->circular_version_id);return view('non-cadre.choice.version',compact('record','items','summary','postMap','search','filter'));}
    public function showItem(int$item,NonCadreChoiceService$service):View{return view('non-cadre.choice.show',$service->itemDetails($item));}
    public function finalize(int$version,Request$request,NonCadreChoiceService$service):RedirectResponse{$v=$service->finalize($version,$request->user()?->id);return back()->with('success',"NC3 Choice dataset v{$v->version} finalized.");}
    public function toggle(int$item,string$code,Request$request,NonCadreChoiceService$service):RedirectResponse{$data=$request->validate(['action'=>['required','in:exclude,restore'],'reason'=>['required','string','min:3','max:2000']]);$service->toggleChoice($item,$code,$data['action'],$data['reason'],$request->user()?->id);return back()->with('success',$data['action']==='exclude'?"Choice {$code} excluded.":"Choice {$code} restored.");}
}
