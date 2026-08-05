@extends('layouts.app')
@section('title','Final Viva Review')
@section('content')
<div class="page-header d-print-none"><div class="container-xl"><div class="row g-2 align-items-center">
<div class="col"><h2 class="page-title">Final Viva Review</h2><div class="text-secondary">Authorized confidential checkpoint before Tabulation/Merit consumes Viva data.</div></div>
<div class="col-auto ms-auto"><a href="{{ route('viva.results.index') }}" class="btn btn-outline-primary">View Confidential Results</a><a href="{{ route('viva.index') }}" class="btn btn-outline-secondary">Back to Viva</a></div>
</div></div></div>
<div class="page-body"><div class="container-xl">
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<div class="alert alert-warning"><div class="fw-semibold">Confidential administrative result</div><div>Viva marks and individual PASS/FAIL are not public publishing output. Finalization creates the authoritative checkpoint for later Tabulation/Merit processing only.</div></div>
@if($state->is_stale)<div class="alert alert-danger">Viva data is outdated: {{ $state->stale_reason ?: 'A result-affecting correction was made.' }}</div>@endif
<div class="row row-cards mb-3">
@foreach(['Total records'=>$summary['total'],'Active'=>$summary['active'],'Appeared'=>$summary['appeared'],'Absent'=>$summary['absent'],'PASS'=>$summary['pass'],'FAIL'=>$summary['fail'],'Not applicable'=>$summary['not_applicable'],'Warnings / review'=>$summary['warnings']] as $label=>$value)
<div class="col-sm-6 col-lg-3"><div class="card h-100"><div class="card-body"><div class="text-secondary">{{ $label }}</div><div class="h2 mb-0">{{ number_format($value) }}</div></div></div></div>
@endforeach
</div>
<div class="card mb-3"><div class="card-header"><h3 class="card-title">Finalization Readiness</h3></div><div class="table-responsive"><table class="table table-vcenter card-table"><tbody>
<tr><td>Current processing state</td><td class="text-end">@if(!$state->is_stale && $state->result_processed_at)<span class="badge bg-green-lt text-green">Current</span>@else<span class="badge bg-red-lt text-red">Not ready</span>@endif</td></tr>
<tr><td>Latest processing run</td><td class="text-end">@if($latestProcessingRun?->status==='completed')<span class="badge bg-green-lt text-green">Completed · Version {{ $latestProcessingRun->processing_version }}</span>@else<span class="badge bg-yellow-lt text-yellow">{{ strtoupper((string)($latestProcessingRun?->status??'not started')) }}</span>@endif</td></tr>
<tr><td>Every Viva record has a derived result</td><td class="text-end">@if($ready)<span class="badge bg-green-lt text-green">Verified</span>@else<span class="badge bg-yellow-lt text-yellow">Check required</span>@endif</td></tr>
<tr><td>Current finalized checkpoint</td><td class="text-end">@if($state->result_finalized_at&&!$state->is_stale)<span class="badge bg-green-lt text-green">Finalized {{ $state->result_finalized_at->timezone('Asia/Dhaka')->format('d M Y, h:i A') }}</span>@else<span class="badge bg-secondary-lt text-secondary">Not finalized</span>@endif</td></tr>
</tbody></table></div></div>
<div class="row row-cards">
<div class="col-lg-7"><div class="card h-100"><div class="card-header"><h3 class="card-title">Authorized Internal Reports</h3></div><div class="card-body">
<p class="text-secondary">Internal verification and Commission review only.</p><div class="d-flex flex-wrap gap-2">
@foreach(['all'=>'All records','pass'=>'PASS','fail'=>'FAIL','absent'=>'ABS','cancelled'=>'Cancelled','withheld'=>'Withheld','expelled'=>'Expelled','warning'=>'Warnings'] as $scope=>$label)
<a href="{{ route('viva.results.export',['scope'=>$scope]) }}" class="btn btn-outline-primary">{{ $label }} XLSX</a>
@endforeach
</div><div class="form-hint mt-3">No Viva TXT export, public marks Excel, or publishing DOCX is provided.</div></div></div></div>
<div class="col-lg-5"><div class="card h-100"><div class="card-header"><h3 class="card-title">Finalize Viva Checkpoint</h3></div>
<form method="post" action="{{ route('viva.finalize') }}">@csrf<div class="card-body">
@if($state->result_finalized_at&&!$state->is_stale)<div class="alert alert-success">Viva is currently finalized. A later result-affecting audited correction will reopen it.</div>@endif
<label class="form-label">Administrative note</label><textarea name="notes" class="form-control mb-3" rows="3">{{ old('notes') }}</textarea>
<label class="form-label required">Confirmation</label><input name="confirmation" class="form-control @error('confirmation') is-invalid @enderror" placeholder="Type FINALIZE" required>
@error('confirmation')<div class="invalid-feedback">{{ $message }}</div>@enderror
<div class="form-hint">Type <strong>FINALIZE</strong> exactly.</div></div>
<div class="card-footer text-end"><button class="btn btn-success" @disabled(!$ready)>Finalize Confidential Viva Result</button></div></form>
</div></div></div>
@if($latestFinalizationRun)<div class="card mt-3"><div class="card-header"><h3 class="card-title">Latest Finalization Snapshot</h3></div><div class="card-body"><div class="row g-3">
<div class="col-md-3"><div class="text-secondary">Processing version</div><div class="fw-bold">{{ $latestFinalizationRun->processing_version }}</div></div>
<div class="col-md-3"><div class="text-secondary">Status</div><div class="fw-bold">{{ strtoupper($latestFinalizationRun->status) }}</div></div>
<div class="col-md-3"><div class="text-secondary">Finalized at</div><div class="fw-bold">{{ $latestFinalizationRun->finalized_at?->timezone('Asia/Dhaka')->format('d M Y, h:i:s A') }}</div></div>
<div class="col-md-3"><div class="text-secondary">Run</div><div class="fw-bold">#{{ $latestFinalizationRun->id }}</div></div>
</div></div></div>@endif
</div></div>
@endsection
