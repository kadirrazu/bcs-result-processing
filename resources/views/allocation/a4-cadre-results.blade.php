@extends('layouts.app')
@section('content')
<style>.a4-cadre-results thead th{font-weight:700}.a4-cadre-results th,.a4-cadre-results td{text-align:center;vertical-align:middle}</style>
<div class="page-header"><div class="container-xl"><div class="d-flex justify-content-between align-items-center gap-3 flex-wrap">
    <div><h2 class="page-title">{{ $cadreAbbreviation }} · {{ $ledger->cadre_code }} — A4 Candidate List</h2><div class="text-secondary">A4 Run v{{ $a4Run->version }} · Circular SL {{ $circularEntry->cadre_serial }}@if($circularEntry->sub_serial!==null).{{ $circularEntry->sub_serial }}@endif</div></div>
    <div class="d-flex gap-2 flex-wrap"><a class="btn btn-outline-primary" href="{{ route('allocation.a4.show',$a4Run) }}">A4 Seat Ledger</a><a class="btn btn-outline-secondary" href="{{ route('allocation.a4.candidates',$a4Run) }}">All Candidate Results</a></div>
</div></div></div>
<div class="page-body"><div class="container-xl">
<div class="card">
    <div class="card-header"><div><h3 class="card-title">{{ $cadreAbbreviation }} Queue / Result List</h3><div class="card-subtitle">Allocated {{ number_format($ledger->total_occupied) }} of {{ number_format($ledger->total_capacity) }} posts · Remaining {{ number_format($ledger->total_remaining) }}</div></div></div>
    <div class="card-body border-bottom"><form method="GET" class="row g-2 align-items-end">
        <div class="col-md-4"><label class="form-label">Registration / Roll</label><input class="form-control" name="reg" value="{{ $reg }}"></div>
        <div class="col-md-2"><label class="form-label">Basis</label><select class="form-select" name="basis"><option value="">All</option>@foreach(['MQ','CFF','EM','PHC'] as $q)<option value="{{ $q }}" @selected($basis===$q)>{{ $q }}</option>@endforeach</select></div>
        <div class="col-md-2"><label class="form-label">Movement</label><select class="form-select" name="movement"><option value="">All</option>@foreach(['DIRECT','NM','SHIFTED'] as $m)<option value="{{ $m }}" @selected($movement===$m)>{{ $m }}</option>@endforeach</select></div>
        <div class="col-md-4"><button class="btn btn-primary">Filter</button> <a class="btn btn-outline-secondary" href="{{ route('allocation.a4.cadre-results',[$a4Run,$circularEntry]) }}">Reset</a></div>
    </form><div class="small text-secondary mt-3">Filtered candidates: {{ number_format($results->total()) }}</div></div>
    <div class="table-responsive"><table class="table table-vcenter mb-0 a4-cadre-results"><thead><tr><th>SL</th><th>Reg</th><th>Choice</th><th>Merit</th><th>Basis</th><th>Movement</th><th>Original Cadre</th><th>Original Choice</th><th>Reason</th></tr></thead><tbody>
        @forelse($results as $row)<tr><td>{{ $results->firstItem()+$loop->index }}</td><td><strong>{{ $row->reg }}</strong></td><td>#{{ str_pad((string)$row->choice_position,2,'0',STR_PAD_LEFT) }}</td><td>{{ number_format($row->merit_position) }}</td><td>{{ $row->allocation_basis }}</td><td>{{ $row->movement_type }}</td><td>{{ $row->original_cadre_code ?: '—' }}</td><td>{{ $row->original_choice_position ? '#'.str_pad((string)$row->original_choice_position,2,'0',STR_PAD_LEFT) : '—' }}</td><td><code>{{ $row->decision_reason }}</code></td></tr>
        @empty<tr><td colspan="9" class="text-center text-secondary py-4">No matching candidate.</td></tr>@endforelse
    </tbody></table></div>@if($results->hasPages())<div class="card-footer">{{ $results->links() }}</div>@endif
</div>
</div></div>
@endsection
