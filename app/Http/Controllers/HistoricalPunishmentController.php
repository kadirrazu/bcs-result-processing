<?php
namespace App\Http\Controllers;
use App\Jobs\ProcessHistoricalPunishmentScreening;
use App\Models\{HistoricalPunishment,HistoricalPunishmentScreeningMatch,HistoricalPunishmentScreeningRun};
use App\Support\Examinations\ExaminationContext;
use App\Services\HistoricalPunishment\{HistoricalPunishmentImportService,HistoricalPunishmentScreeningService};
use Illuminate\Http\{Request,RedirectResponse};
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
final class HistoricalPunishmentController extends Controller
{
    public function index(Request $r): View
    {
        $q=trim((string)$r->query('search')); $status=(string)$r->query('status','all'); $today=now()->toDateString();
        $activeScope = fn($x) => $x->where(fn($z) => $z
            ->where('is_lifetime', 1)
            ->orWhere(fn($d) => $d->where('is_lifetime', 0)
                ->whereNotNull('punishment_end')->where('punishment_end', '>=', $today)
                ->where(fn($a) => $a->whereNull('punishment_start')->orWhere('punishment_start', '<=', $today))));
        $summary = [
            'total' => HistoricalPunishment::query()->count(),
            'active' => tap(HistoricalPunishment::query(), $activeScope)->count(),
            'lifetime' => HistoricalPunishment::query()->where('is_lifetime', 1)->count(),
            'expired' => HistoricalPunishment::query()->where('is_lifetime', 0)->whereNotNull('punishment_end')->where('punishment_end', '<', $today)->count(),
        ];
        $rows=HistoricalPunishment::query()
            ->when($q!=='',fn($x)=>$x->where(fn($z)=>$z->where('bcs',$q)->orWhere('reg','like',"%{$q}%")->orWhere('name','like',"%{$q}%")->orWhere('nid_no','like',"%{$q}%")))
            ->when($status==='active',fn($x)=>$x->where(fn($z)=>$z->where('is_lifetime',1)->orWhere(fn($d)=>$d->where('is_lifetime',0)->whereNotNull('punishment_end')->where('punishment_end','>=',$today)->where(fn($a)=>$a->whereNull('punishment_start')->orWhere('punishment_start','<=',$today)))))
            ->when($status==='expired',fn($x)=>$x->where('is_lifetime',0)->whereNotNull('punishment_end')->where('punishment_end','<',$today))
            ->when($status==='lifetime',fn($x)=>$x->where('is_lifetime',1))
            ->orderByDesc('id')->paginate(25)->withQueryString();
        return view('historical-punishments.index',compact('rows','q','status','today','summary'));
    }
    public function create(): View { return view('historical-punishments.create'); }
    public function edit(HistoricalPunishment $historicalPunishment): View { return view('historical-punishments.edit',compact('historicalPunishment')); }
    public function importPage(): View { return view('historical-punishments.import'); }
    public function store(Request $r):RedirectResponse{$d=$r->validate(['bcs'=>'required|integer|min:1|max:999','reg'=>'nullable|string|max:40','name'=>'required|string|max:255','fname'=>'nullable|string|max:255','mname'=>'nullable|string|max:255','b_date'=>'required|date','dob'=>'nullable|date','dist_name'=>'nullable|string|max:255','ssc_roll'=>'required|string|max:80','ssc_year'=>'required|integer|min:1900|max:2200','hsc_roll'=>'nullable|string|max:80','hsc_year'=>'nullable|integer|min:1900|max:2200','nid_no'=>'nullable|string|max:80','is_lifetime'=>'nullable|boolean','punishment_start'=>'nullable|date','punishment_end'=>'nullable|date|after_or_equal:punishment_start','punishment_reason'=>'nullable|string|max:5000']);$life=$r->boolean('is_lifetime');if(!$life&&!$r->filled('punishment_end'))return back()->withErrors(['punishment_end'=>'Punishment end date is required unless Lifetime is selected. Start date may be blank.'])->withInput();$d['is_lifetime']=$life;$d['punishment_start']=$life?null:($d['punishment_start']??null);$d['punishment_end']=$life?null:($d['punishment_end']??null);$d['created_by']=$r->user()->id;$d['updated_by']=$r->user()->id;HistoricalPunishment::query()->create($d);return back()->with('success','Historical punishment record added.');}
    public function update(Request $r,HistoricalPunishment $historicalPunishment):RedirectResponse{$d=$r->validate(['bcs'=>'required|integer|min:1|max:999','reg'=>'nullable|string|max:40','name'=>'required|string|max:255','fname'=>'nullable|string|max:255','mname'=>'nullable|string|max:255','b_date'=>'required|date','dob'=>'nullable|date','dist_name'=>'nullable|string|max:255','ssc_roll'=>'required|string|max:80','ssc_year'=>'required|integer|min:1900|max:2200','hsc_roll'=>'nullable|string|max:80','hsc_year'=>'nullable|integer|min:1900|max:2200','nid_no'=>'nullable|string|max:80','is_lifetime'=>'nullable|boolean','punishment_start'=>'nullable|date','punishment_end'=>'nullable|date|after_or_equal:punishment_start','punishment_reason'=>'nullable|string|max:5000']);$life=$r->boolean('is_lifetime');if(!$life&&!$r->filled('punishment_end'))return back()->withErrors(['punishment_end'=>'Punishment end date is required unless Lifetime is selected. Start date may be blank.']);$d['is_lifetime']=$life;$d['punishment_start']=$life?null:($d['punishment_start']??null);$d['punishment_end']=$life?null:($d['punishment_end']??null);$d['updated_by']=$r->user()->id;$historicalPunishment->update($d);return back()->with('success','Record updated.');}
    public function destroy(HistoricalPunishment $historicalPunishment):RedirectResponse{$historicalPunishment->delete();return back()->with('success','Record deleted.');}
    public function import(Request $r,HistoricalPunishmentImportService $s):RedirectResponse{$r->validate(['file'=>'required|file|mimes:xlsx,xls|max:20480']);$n=$s->import($r->file('file'),$r->user()->id);return back()->with('success',"Imported {$n} punishment records.");}
    public function template(){ $s=new Spreadsheet();$sh=$s->getActiveSheet();$sh->fromArray([['bcs','reg','name','fname','mname','b_date','dob','dist_name','ssc_roll','ssc_year','hsc_roll','hsc_year','nid_no','is_lifetime','punishment_start','punishment_end','punishment_reason'],[46,'12345678','Sample Lifetime Candidate','','','101097','1997-10-10','Dhaka','123456','2012','654321','2014','','TRUE','','','Sample lifetime punishment'],[53,'87654321','Sample Fixed-Term Candidate','','','150890','1990-08-15','Dhaka','223344','2006','556677','2008','','FALSE','2026-01-15','2028-12-31','Sample fixed-term punishment']]);return $this->xlsx($s,'historical-punishment-import-template.xlsx');}
    public function screening(Request $request):View{$selectedPhase=(string)$request->query('phase','preliminary_initial');$runs=HistoricalPunishmentScreeningRun::query()->when($selectedPhase!=='',fn($q)=>$q->where('phase',$selectedPhase))->latest('id')->limit(20)->get();return view('historical-punishments.screening',compact('runs','selectedPhase'));}
    public function run(Request $r, HistoricalPunishmentScreeningService $s, ExaminationContext $context): RedirectResponse
    {
        $d = $r->validate([
            'phase' => 'required|in:preliminary_initial,preliminary_final,written_initial,written_final,merit_initial,merit_final,allocation_final,noncadre_final',
            'reference_date' => 'required|date',
        ]);

        $existing = HistoricalPunishmentScreeningRun::query()
            ->where('phase', $d['phase'])
            ->whereIn('status', ['queued', 'running'])
            ->latest('id')
            ->first();
        if ($existing) {
            return redirect()->route('historical-punishments.screening.show', $existing)
                ->with('success', 'A screening job for this population is already queued/running.');
        }

        $run = $s->createRun($d['phase'], $d['reference_date'], (int) $r->user()->id);
        ProcessHistoricalPunishmentScreening::dispatch((int) $context->currentId(), (int) $run->id);

        return redirect()->route('historical-punishments.screening.show', $run)
            ->with('success', 'Screening queued. Processing will continue in the background.');
    }
    public function screeningStatus(HistoricalPunishmentScreeningRun $run)
    {
        $run->refresh();

        return response()->json([
            'id' => (int) $run->id,
            'status' => (string) $run->status,
            'candidate_count' => (int) $run->candidate_count,
            'processed_count' => (int) ($run->processed_count ?? 0),
            'progress_percent' => $run->progressPercent(),
            'matched_count' => (int) $run->matched_count,
            'review_count' => (int) $run->review_count,
            'active_warning_count' => (int) $run->active_warning_count,
            'failure_message' => $run->failure_message,
            'finished_at' => $run->finished_at?->toIso8601String(),
        ]);
    }

    public function showScreening(HistoricalPunishmentScreeningRun $run):View{$matches=HistoricalPunishmentScreeningMatch::query()->where('run_id',$run->id)->orderByDesc('punishment_active')->orderBy('match_status')->paginate(50);$hist=HistoricalPunishment::query()->whereIn('id',$matches->pluck('historical_punishment_id'))->get()->keyBy('id');return view('historical-punishments.screening-show',compact('run','matches','hist'));}
    public function review(Request $r,HistoricalPunishmentScreeningMatch $match):RedirectResponse{$d=$r->validate(['decision'=>'required|in:matched,rejected','review_note'=>'required|string|max:2000']);$match->update(['match_status'=>$d['decision'],'reviewed_by'=>$r->user()->id,'reviewed_at'=>now(),'review_note'=>$d['review_note']]);$run=HistoricalPunishmentScreeningRun::query()->findOrFail($match->run_id);$run->update(['matched_count'=>HistoricalPunishmentScreeningMatch::query()->where('run_id',$run->id)->where('match_status','matched')->count(),'review_count'=>HistoricalPunishmentScreeningMatch::query()->where('run_id',$run->id)->where('match_status','review')->count(),'active_warning_count'=>HistoricalPunishmentScreeningMatch::query()->where('run_id',$run->id)->where('match_status','matched')->where('punishment_active',1)->count()]);return back()->with('success','Match review saved.');}
    public function export(HistoricalPunishmentScreeningRun $run){abort_unless($run->status==='completed',409,'Screening is not completed yet.');$rows=HistoricalPunishmentScreeningMatch::query()->where('run_id',$run->id)->where('match_status','matched')->where('punishment_active',1)->orderBy('reg')->get();$hist=HistoricalPunishment::query()->get()->keyBy('id');$s=new Spreadsheet();$sh=$s->getActiveSheet();$sh->fromArray([['user_id','reg','name','previous_bcs','previous_reg','match_status','punishment_type','punishment_start','punishment_end','punishment_reason','reference_date']]);$i=2;foreach($rows as $m){$h=$hist[$m->historical_punishment_id]??null;if(!$h)continue;$sh->fromArray([[$m->user_id,$m->reg,$m->name,$h->bcs,$h->reg,$m->match_status,$h->is_lifetime?'LIFETIME':'FIXED',$h->punishment_start?->format('Y-m-d'),$h->punishment_end?->format('Y-m-d'),$h->punishment_reason,$run->reference_date->format('Y-m-d')]],null,"A{$i}");$i++;}return $this->xlsx($s,"punishment-screening-{$run->phase}-{$run->id}.xlsx");}
    private function xlsx(Spreadsheet $s,string $name){$tmp=tempnam(sys_get_temp_dir(),'xlsx');(new Xlsx($s))->save($tmp);return response()->download($tmp,$name)->deleteFileAfterSend(true);}
}
