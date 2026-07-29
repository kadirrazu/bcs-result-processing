<?php
namespace App\Http\Controllers;
use App\Enums\CadreCategory;
use App\Models\Registration;
use Illuminate\Support\Facades\DB;
/** Fast stage reports use SQL aggregation rather than loading candidate collections. */
final class RegistrationReportController extends Controller
{
 public function __invoke(){ $this->authorize('viewAny',Registration::class);$q=DB::connection('exam')->table('registrations');return view('registrations.report',['total'=>(clone $q)->count(),'quota'=>(clone $q)->where('has_quota',1)->count(),'categories'=>(clone $q)->select('cadre_category',DB::raw('COUNT(*) total'))->groupBy('cadre_category')->pluck('total','cadre_category'),'sex'=>(clone $q)->select('sex_code',DB::raw('COUNT(*) total'))->groupBy('sex_code')->orderBy('sex_code')->get(),'statuses'=>(clone $q)->select('status',DB::raw('COUNT(*) total'))->groupBy('status')->get()]);}
}
