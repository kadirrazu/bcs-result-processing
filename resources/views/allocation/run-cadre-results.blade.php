@extends('layouts.app')
@section('content')
<style>
.a3-cadre-results th,.a3-cadre-results td{text-align:center;vertical-align:middle}
.a3-cadre-results td.a3-reg-cell{text-align:left!important}
</style>
<div class="page-header"><div class="container-xl"><div class="d-flex justify-content-between align-items-center gap-3 flex-wrap">
    <div>
        <h2 class="page-title">A3 Phase-1 — {{ $ledger->cadre_code }} - {{ $abbr }}</h2>
        <div class="text-secondary">Run v{{ $run->version }} · Candidate results for this exact Circular cadre/post.</div>
    </div>
    <div class="d-inline-flex gap-2 flex-nowrap">
        <a class="btn btn-sm btn-outline-primary text-nowrap" href="{{ route('allocation.runs.show',$run) }}">View A3 Seat Ledger</a>
        <a class="btn btn-sm btn-outline-secondary text-nowrap" href="{{ route('allocation.index') }}">Back to Allocation</a>
    </div>
</div></div></div>
<div class="page-body"><div class="container-xl">
<div class="card">
    <div class="card-header"><div><h3 class="card-title">Phase-1 Candidate Results</h3><div class="card-subtitle">{{ $ledger->cadre_code }} - {{ $abbr }}</div></div></div>
    <div class="card-body border-bottom">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4"><label class="form-label">Registration / Roll</label><input class="form-control" name="reg" value="{{ $reg }}"></div>
            <div class="col-md-3"><label class="form-label">Basis</label><select class="form-select" name="basis"><option value="">All</option>@foreach(['MQ','CFF','EM','PHC'] as $q)<option value="{{ $q }}" @selected($basis===$q)>{{ $q }}</option>@endforeach</select></div>
            <div class="col-md-3"><label class="form-label">Status</label><select class="form-select" name="decision_status"><option value="">All</option><option value="FINAL" @selected($decisionStatus==='FINAL')>FINAL</option><option value="TEMPORARY" @selected($decisionStatus==='TEMPORARY')>TEMPORARY</option></select></div>
            <div class="col-md-2"><button class="btn btn-primary">Filter</button></div>
        </form>
        <div class="small text-secondary mt-3">Filtered results: {{ number_format($results->total()) }}</div>
    </div>
    <div class="table-responsive"><table class="table table-vcenter mb-0 a3-cadre-results">
        <thead><tr><th>SL</th><th>Reg</th><th>Choice</th><th>Merit</th><th>Source</th><th>Basis</th><th>Movement</th><th>Status</th></tr></thead>
        <tbody>
        @forelse($results as $row)
            <tr>
                <td>{{ $results->firstItem()+$loop->index }}</td>
                <td class="a3-reg-cell"><strong>{{ $row->reg }}</strong></td>
                <td>#{{ str_pad((string)$row->choice_position,2,'0',STR_PAD_LEFT) }}</td>
                <td>{{ number_format($row->merit_position) }}</td>
                <td><code>{{ $row->merit_source }}</code></td>
                <td><span class="badge bg-{{ $row->allocation_basis==='MQ'?'azure':'purple' }}-lt">{{ $row->allocation_basis }}</span></td>
                <td>{{ $row->movement_type }}</td>
                <td><span class="badge bg-{{ $row->decision_status==='FINAL'?'success':'warning' }}-lt">{{ $row->decision_status }}</span></td>
            </tr>
        @empty
            <tr><td colspan="8" class="text-center text-secondary py-4">No matching Phase-1 candidate result.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    @if($results->hasPages())<div class="card-footer">{{ $results->links() }}</div>@endif
</div>
</div></div>
@endsection
