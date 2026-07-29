<?php
namespace App\Http\Controllers;
use App\Models\Registration;
use App\Services\Registrations\{RegistrationImportService,RegistrationTemplateService};
use Illuminate\Http\{RedirectResponse,Request};
use Symfony\Component\HttpFoundation\BinaryFileResponse;
/** Registration Excel import endpoints. */
final class RegistrationImportController extends Controller
{
 public function create(){ $this->authorize('import',Registration::class);return view('registrations.import');}
 public function store(Request $r,RegistrationImportService $service): RedirectResponse{$this->authorize('import',Registration::class);$r->validate(['file'=>['required','file','mimes:xlsx,xls','max:51200']]);$batch=$service->import($r->file('file'),$r->user()->id);return redirect()->route('registrations.import-result',$batch)->with('success','Import completed.');}
 public function template(RegistrationTemplateService $service): BinaryFileResponse{$this->authorize('viewAny',Registration::class);$path=storage_path('app/registration-template.xlsx');$service->create($path);return response()->download($path,'registration-import-template.xlsx')->deleteFileAfterSend();}
 public function result(int $batch){$this->authorize('viewAny',Registration::class);$record=\App\Models\RegistrationImportBatch::query()->findOrFail($batch);$errors=session('registration_import_errors_'.$batch,[]);return view('registrations.import-result',compact('record','errors'));}
}
