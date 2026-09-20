<?php
namespace App\Http\Controllers\NonCadre\Allocation;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessNonCadreAllocationStage;
use App\Services\NonCadre\Allocation\NonCadreAllocationService;
use App\Support\Examinations\ExaminationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class NonCadreAllocationController extends Controller
{
    public function index(NonCadreAllocationService $s):View{$prerequisites=$s->prerequisites();$runs=$s->runs();$latest=$runs->first();return view('non-cadre.allocation.index',['prerequisites'=>$prerequisites,'runs'=>$runs,'statusBoard'=>$s->statusBoard($latest,$prerequisites)]);}
    public function freeze(Request $r,NonCadreAllocationService $s,ExaminationContext $context):RedirectResponse{$exam=$context->current();abort_if(!$exam,409);$run=$s->enqueueFreeze($r->user()?->id);ProcessNonCadreAllocationStage::dispatch((int)$exam->id,(int)$run->id,'INPUT_FREEZE',$r->user()?->id);return redirect()->route('non-cadre.allocation.run',$run->id)->with('success','NC4.1 Input Freeze queued. You may leave this page while the worker processes it.');}
    public function run(int $run,Request $request,NonCadreAllocationService $s):View{$filters=$request->only(['search','post_code','basis','movement','allocation']);return view('non-cadre.allocation.run',['run'=>$s->run($run),'results'=>$s->results($run,$filters),'checks'=>$s->checks($run),'reviews'=>$s->reviews($run),'summary'=>$s->allocationSummary($run),'postSummary'=>$s->postWiseSummary($run),'filters'=>$filters]);}
    public function progress(int $run,NonCadreAllocationService $s):JsonResponse{return response()->json($s->progress($run));}
    public function phase1(int $run,Request $r,NonCadreAllocationService $s,ExaminationContext $context):RedirectResponse{return $this->queue($run,'PHASE1','NC4.2 Phase-1 queued.',$r,$s,$context);}
    public function phase2(int $run,Request $r,NonCadreAllocationService $s,ExaminationContext $context):RedirectResponse{return $this->queue($run,'PHASE2','NC4.3 Shifting + NM queued.',$r,$s,$context);}
    public function validateAllocation(int $run,Request $r,NonCadreAllocationService $s,ExaminationContext $context):RedirectResponse{return $this->queue($run,'VALIDATION','NC4.4 Allocation Validation queued.',$r,$s,$context);}
    public function review(int $run,int $review,Request $r,NonCadreAllocationService $s,ExaminationContext $context):RedirectResponse{$d=$r->validate(['decision'=>['required','in:APPROVED,REJECTED'],'reason'=>['required','string','max:2000']]);$exam=$context->current();abort_if(!$exam,409);$s->review($run,$review,$d['decision'],$d['reason'],$r->user()?->id);$s->queueStage($run,'RECOMPUTE');ProcessNonCadreAllocationStage::dispatch((int)$exam->id,$run,'RECOMPUTE',$r->user()?->id);return back()->with('success','Special Requirement decision saved. Phase-1 + Phase-2 recalculation queued.');}
    public function finalize(int $run,Request $r,NonCadreAllocationService $s):RedirectResponse{$s->finalize($run,$r->user()?->id);return back()->with('success','NC4 Non-Cadre Allocation finalized.');}
    private function queue(int $run,string $stage,string $message,Request $r,NonCadreAllocationService $s,ExaminationContext $context):RedirectResponse{$exam=$context->current();abort_if(!$exam,409);$s->queueStage($run,$stage);ProcessNonCadreAllocationStage::dispatch((int)$exam->id,$run,$stage,$r->user()?->id);return back()->with('success',$message.' You may leave this page while it processes.');}
}
