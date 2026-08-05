@extends('layouts.app')
@section('title', 'Viva Candidate Data')
@section('content')
<div class="page-header d-print-none">
    <div class="container-xl">
        <div class="row g-2 align-items-center">
            <div class="col">
                <h2 class="page-title">Viva Candidate Data</h2>
                <div class="text-secondary">Search, review and make audited manual corrections to approved Viva Board data.</div>
            </div>
            <div class="col-auto ms-auto">
                <a href="{{ route('viva.reviews') }}" class="btn btn-outline-warning">Review Warnings</a>
                <a href="{{ route('viva.index') }}" class="btn btn-outline-secondary">Back to Viva</a>
            </div>
        </div>
    </div>
</div>

<div class="page-body">
<div class="container-xl">
    <div class="alert alert-info">
        Original imported <strong>raw source values are never overwritten</strong>. Manual correction changes only the effective Viva data and is recorded in both the database audit trail and Viva file log.
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form method="get" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">Search</label>
                    <input name="search" class="form-control" value="{{ $search }}" placeholder="REG, User, Viva Code or name">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Attendance</label>
                    <select name="attendance" class="form-select">
                        <option value="">All</option>
                        <option value="appeared" @selected($attendance === 'appeared')>Appeared</option>
                        <option value="absent" @selected($attendance === 'absent')>Absent</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All</option>
                        @foreach(['active'=>'Active','cancelled'=>'Cancelled','withheld'=>'Withheld','expelled'=>'Expelled'] as $value=>$label)
                            <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Review</label>
                    <select name="review" class="form-select">
                        <option value="">All</option>
                        <option value="warning" @selected($review === 'warning')>Warnings first / only</option>
                    </select>
                </div>
                <div class="col-auto"><button class="btn btn-primary">Filter</button></div>
                <div class="col-auto"><a href="{{ route('viva.candidates.index') }}" class="btn btn-outline-secondary">Clear</a></div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title">Approved Viva Board records</h3>
                <div class="text-secondary small">
                    Displaying {{ number_format($rows->firstItem() ?? 0) }} to {{ number_format($rows->lastItem() ?? 0) }}
                    of {{ number_format($rows->total()) }} records
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th>REG / USER</th>
                        <th>CODE</th>
                        <th>NAME</th>
                        <th>TRACK</th>
                        <th>DATE</th>
                        <th>MEMBER</th>
                        <th>MARK</th>
                        <th>VIVA QUOTA</th>
                        <th>STATUS</th>
                        <th>REVIEW</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @forelse($rows as $row)
                    @php
                        $statusValue = $row->status instanceof \BackedEnum ? $row->status->value : (string) $row->status;
                        $track = $row->written_qualified_track instanceof \BackedEnum ? $row->written_qualified_track->value : $row->written_qualified_track;
                        $warnings = (bool)$row->quota_mismatch || (bool)$row->invalid_flag || (bool)$row->issue_flag || (bool)$row->high_mark_review || (($row->validation_status instanceof \BackedEnum ? $row->validation_status->value : (string)$row->validation_status) === 'warning');
                        $vivaQuota=[]; if($row->viva_cff)$vivaQuota[]='CFF'; if($row->viva_em)$vivaQuota[]='EM'; if($row->viva_phc)$vivaQuota[]='PHC';
                    @endphp
                    <tr class="{{ $warnings ? 'table-warning' : '' }}">
                        <td><div class="fw-semibold">{{ $row->reg }}</div><div class="text-secondary small">{{ $row->user_id }}</div></td>
                        <td>{{ $row->code }}</td>
                        <td>{{ $row->name ?? '—' }}</td>
                        <td>{{ $track }}</td>
                        <td>{{ $row->viva_date?->format('d-m-Y') ?? '—' }}</td>
                        <td>{{ $row->member_id }}</td>
                        <td>{{ $row->attendance_status === 'absent' ? 'ABS' : number_format((float)$row->mark, 2) }}</td>
                        <td>{{ $vivaQuota ? implode(', ', $vivaQuota) : 'None' }}</td>
                        <td><span class="badge {{ $statusValue === 'active' ? 'bg-green-lt text-green' : 'bg-secondary-lt text-secondary' }}">{{ strtoupper($statusValue) }}</span></td>
                        <td>
                            @if($warnings)
                                <span class="badge bg-yellow-lt text-yellow">Needs review</span>
                            @else
                                <span class="badge bg-green-lt text-green">Clear</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <a href="{{ route('viva.candidates.edit', $row->id) }}" class="btn btn-sm btn-outline-primary">View / Edit</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="11" class="text-center text-secondary py-4">No Viva Board records match the selected filters.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($rows->hasPages())
            <div class="card-footer">{{ $rows->links() }}</div>
        @endif
    </div>
</div>
</div>
@endsection
