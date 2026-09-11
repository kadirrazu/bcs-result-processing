@extends('layouts.app')
@section('title', 'A5.5 — '.$status.' Candidate List')

@section('content')
<style>
@media print{
    .no-print,.navbar,.page-header .btn{display:none!important}
    .page-body{margin:0!important;padding:0!important}
    .container-xl{max-width:none!important;padding:0!important}
    .card{border:0!important;box-shadow:none!important}
    .table-responsive{overflow:visible!important}
    @page{size:A4 landscape;margin:.5in}
}
.a55-reason{min-width:260px;max-width:420px;white-space:normal}
</style>

<div class="page-header">
    <div class="container-xl">
        <div class="row align-items-center">
            <div class="col">
                <div class="page-pretitle">A5.5 — Result Disposition / Publication Control</div>
                <h2 class="page-title">{{ $status }} Candidate List</h2>
                <div class="text-secondary">
                    {{ $status === 'WITHHELD'
                        ? 'Allocated candidates currently withheld from publication.'
                        : 'Allocated candidates cancelled from publication.' }}
                </div>
            </div>
            <div class="col-auto ms-auto d-flex gap-2 no-print">
                <button type="button" class="btn btn-primary" onclick="window.print()">Print List</button>
                <a class="btn btn-outline-secondary" href="{{ route('allocation.disposition.index') }}">Back to A5.5</a>
            </div>
        </div>
    </div>
</div>

<div class="page-body">
<div class="container-xl">
    <div class="card mb-3 no-print">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-5">
                    <label class="form-label">Search by Reg / Name</label>
                    <input class="form-control" type="search" name="search" value="{{ $search }}" placeholder="Registration number or candidate name">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Filter by Cadre</label>
                    <select class="form-select" name="cadre_code">
                        <option value="0">All Cadres</option>
                        @foreach($cadreOptions as $cadre)
                            <option value="{{ $cadre['code'] }}" @selected((int)$cadreCode === (int)$cadre['code'])>
                                {{ $cadre['code'] }} - {{ $cadre['abbr'] }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto"><button class="btn btn-primary">Filter</button></div>
                <div class="col-auto">
                    <a class="btn btn-outline-secondary" href="{{ route('allocation.disposition.list', ['status' => $status]) }}">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title">{{ $status }} Candidates</h3>
                <div class="small text-secondary">
                    Total shown: <strong>{{ number_format($rows->count()) }}</strong>
                    @if($cadreCode > 0 || $search !== '') · Filtered list @endif
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-vcenter mb-0">
                <thead>
                <tr>
                    <th class="text-center">Sl.</th>
                    <th>Registration No.</th>
                    <th>Name</th>
                    <th>Date of Birth</th>
                    <th>Cadre</th>
                    <th class="text-center">Merit Position</th>
                    <th class="text-center">Basis</th>
                    <th class="text-center">Status</th>
                    <th>Reason</th>
                    <th class="text-end no-print"></th>
                </tr>
                </thead>
                <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td class="text-center">{{ $loop->iteration }}</td>
                        <td><strong>{{ $row->reg }}</strong></td>
                        <td>{{ $row->candidate_name }}</td>
                        <td>{{ $row->candidate_birth_date ? \Carbon\Carbon::parse($row->candidate_birth_date)->format('d-m-Y') : '—' }}</td>
                        <td><strong>{{ $row->cadre_code }} - {{ $abbr->get((int)$row->cadre_code, '—') }}</strong></td>
                        <td class="text-center">{{ $row->merit_position ?? '—' }}</td>
                        <td class="text-center">{{ strtoupper((string)$row->allocation_basis) }}</td>
                        <td class="text-center">
                            <span class="badge bg-{{ $status === 'WITHHELD' ? 'warning' : 'danger' }}-lt">{{ $status }}</span>
                        </td>
                        <td class="a55-reason">{{ $row->reason ?: '—' }}</td>
                        <td class="text-end no-print">
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('allocation.disposition.show', $row->registration_id) }}">Review</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="text-center text-secondary py-4">No {{ $status }} candidates matched the current filters.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>
@endsection
