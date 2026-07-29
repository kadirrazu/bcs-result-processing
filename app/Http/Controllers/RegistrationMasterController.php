<?php
namespace App\Http\Controllers;
use Illuminate\Http\{RedirectResponse,Request};
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
/** Small config-driven CRUD for registration-only central reference masters. */
final class RegistrationMasterController extends Controller
{
 public function index(Request $r,string $type): View{$d=$this->definition($type);abort_unless($r->user()->isAdmin(),403);$q=$d['model']::query();if($s=trim((string)$r->query('search')))$q->where(fn($x)=>$x->where('name','like',"%{$s}%")->orWhere('code',$s));return view('registration-masters.index',['type'=>$type,'definition'=>$d,'records'=>$q->orderBy('code')->paginate(50)->withQueryString(),'search'=>$s??'']);}
 public function create(Request $r,string $type): View{$d=$this->definition($type);abort_unless($r->user()->isAdmin(),403);return view('registration-masters.form',['type'=>$type,'definition'=>$d,'record'=>null]);}
 public function store(Request $r,string $type): RedirectResponse{$d=$this->definition($type);abort_unless($r->user()->isAdmin(),403);$d['model']::query()->create($this->validated($r,$d));return redirect()->route('registration-masters.index',$type)->with('success','Master record created.');}
 public function edit(Request $r,string $type,int $id): View{$d=$this->definition($type);abort_unless($r->user()->isAdmin(),403);return view('registration-masters.form',['type'=>$type,'definition'=>$d,'record'=>$d['model']::query()->findOrFail($id)]);}
 public function update(Request $r,string $type,int $id): RedirectResponse{$d=$this->definition($type);abort_unless($r->user()->isAdmin(),403);$d['model']::query()->findOrFail($id)->update($this->validated($r,$d,$id));return redirect()->route('registration-masters.index',$type)->with('success','Master record updated.');}
 private function definition(string $type): array{return config("registration-masters.{$type}")??throw new NotFoundHttpException();}
 private function validated(Request $r,array $d,?int $id=null): array{$rules=['code'=>['required','integer','min:1'],'name'=>['required','string','max:255'],'name_bn'=>['nullable','string','max:255'],'is_active'=>['nullable','boolean']];if(in_array('division_code',$d['fields'],true))$rules['division_code']=['required','integer','min:1'];$v=$r->validate($rules);$v['is_active']=$r->boolean('is_active');return $v;}
}
