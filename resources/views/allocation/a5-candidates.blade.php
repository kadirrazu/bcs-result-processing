@extends('layouts.app')
@section('content')
<style>
    .a5-candidate-table th,.a5-candidate-table td{text-align:center;vertical-align:middle}
    .a5-candidate-table .a5-reg-cell{text-align:left!important}
</style>
<div class="page-header"><div class="container-xl"><div class="d-flex justify-content-between align-items-center gap-3 flex-wrap">
    <div>
        <h2 class="page-title">Candidate Validity Report · A5 v{{ $a5Run->version }}</h2>
        <div class="text-secondary">{{ $a5Run->status === 'finalized' ? 'POST-FINALIZED / FINALIZED VALIDATION REPORT' : 'PRE-FINALIZED VALIDATION REPORT' }}</div>
    </div>
    <div class="d-flex gap-2 flex-wrap"><a class="btn btn-outline-primary" href="{{ route('allocation.a5.show',$a5Run) }}">A5 Validation Summary</a><a class="btn btn-outline-secondary" href="{{ route('allocation.index') }}">Back to Allocation</a></div>
</div></div></div>
<div class="page-body"><div class="container-xl">
@if((bool)$a5Run->is_stale)<div class="alert alert-warning"><strong>STALE / OUTDATED.</strong> {{ $a5Run->stale_reason }}</div>@endif
<div class="row row-cards mb-3">
    <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="text-secondary">Total Checked</div><div class="h2 mb-0">{{ number_format($a5Run->total_allocated) }}</div></div></div></div>
    <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="text-secondary">PASS</div><div class="h2 mb-0 text-success">{{ number_format($a5Run->candidate_passed) }}</div></div></div></div>
    <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="text-secondary">FAIL</div><div class="h2 mb-0 {{ $a5Run->candidate_failed ? 'text-danger' : 'text-success' }}">{{ number_format($a5Run->candidate_failed) }}</div></div></div></div>
    <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="text-secondary">Report State</div><div class="fw-semibold">{{ $a5Run->status === 'finalized' ? 'FINALIZED' : 'PRE-FINALIZED' }}</div><div class="small text-secondary">Source A4 v{{ $a5Run->a4Run?->version }} · Circular v{{ $a5Run->circular_version }}</div></div></div></div>
</div>
<div class="card">
    <div class="card-header"><div><h3 class="card-title">Candidate Validity Report</h3><div class="card-subtitle">Bachelor + PRS + Technical/Professional + Quota eligibility evidence. A5 remains read-only.</div></div></div>
    <div class="card-body border-bottom"><form method="GET" class="row g-2 align-items-end">
        <div class="col-md-4"><label class="form-label">Reg / Cadre Code / Abbreviation</label><input class="form-control" name="search" value="{{ $search }}" placeholder="e.g. 12345678, 110 or ADMN"></div>
        <div class="col-md-4"><label class="form-label">Cadre Filter</label><select class="form-select" name="cadre_code"><option value="">All Cadres</option>@foreach($cadreOptions as $option)<option value="{{ $option['code'] }}" @selected((string)$cadreCode===(string)$option['code'])>{{ $option['code'] }}@if($option['abbr']!=='') - {{ $option['abbr'] }}@endif</option>@endforeach</select></div>
        <div class="col-md-2"><label class="form-label">Overall Status</label><select class="form-select" name="status"><option value="">All</option><option value="PASS" @selected($status==='PASS')>PASS</option><option value="FAIL" @selected($status==='FAIL')>FAIL</option></select></div>
        <div class="col-md-2"><button class="btn btn-primary">Filter</button> <a class="btn btn-outline-secondary" href="{{ route('allocation.a5.candidates',$a5Run) }}">Reset</a></div>
    </form><div class="small text-secondary mt-3">Filtered candidates: {{ number_format($results->total()) }}</div></div>
    <div class="table-responsive"><table class="table table-vcenter mb-0 a5-candidate-table"><thead><tr><th>SL</th><th class="text-start">Reg</th><th>Cadre</th><th>Merit Position</th><th>Basis</th><th>Bachelor</th><th>PRS</th><th>Technical</th><th>Quota</th><th>Status / Reason</th></tr></thead><tbody>
    @forelse($results as $row)
        @php $abbr=(string)$abbreviationByCode->get((int)$row->cadre_code,'—'); @endphp
        <tr>
            <td>{{ $results->firstItem()+$loop->index }}</td>
            <td class="a5-reg-cell fw-bold">{{ $row->reg }}</td>
            <td><strong>{{ $row->cadre_code }} - {{ $abbr }}</strong></td>
            <td>{{ $row->merit_position !== null ? number_format((int) $row->merit_position) : '—' }}</td>
            <td>{{ $row->allocation_basis }}</td>
            @foreach(['bachelor_status','prs_status','technical_status','quota_status'] as $field)<td><span class="badge bg-{{ $row->$field==='PASS'?'success':($row->$field==='FAIL'?'danger':'secondary') }}-lt">{{ $row->$field }}</span></td>@endforeach
            <td><span class="badge bg-{{ $row->overall_status==='PASS'?'success':'danger' }}-lt">{{ $row->overall_status }}</span>@if($row->reason_codes)<div class="small text-danger mt-1">{{ implode(', ', $row->reason_codes) }}</div>@endif</td>
        </tr>
    @empty<tr><td colspan="10" class="text-center text-secondary py-4">No matching validation rows.</td></tr>@endforelse
    </tbody></table></div>@if($results->hasPages())<div class="card-footer">{{ $results->links() }}</div>@endif
</div>
</div></div>
@endsection
