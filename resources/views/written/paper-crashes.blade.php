@extends('layouts.app')
@section('title','Written Paper Crash Report')
@section('content')
<div class="page-header d-print-none"><div class="container-xl"><div class="row g-2 align-items-center">
    <div class="col"><h2 class="page-title">Written Paper Crash Report</h2><div class="text-secondary">Actual marks are preserved. A crashed paper contributes zero to the counted total.</div></div>
    <div class="col-auto d-flex gap-2"><a class="btn btn-outline-secondary" href="{{ route('written.index') }}">Back to Written</a><a class="btn btn-outline-success" href="{{ route('written.paper-crashes.xlsx', request()->query()) }}">Export XLSX</a><a class="btn btn-outline-secondary" href="{{ route('written.paper-crashes.csv', request()->query()) }}">Export CSV</a></div>
</div></div></div>
<div class="page-body"><div class="container-xl">
    <div class="row row-cards mb-3"><div class="col-md-4"><div class="card card-sm border-warning"><div class="card-body"><div class="text-secondary">Candidates with at least one Paper Crash</div><div class="h2 text-warning mb-0">{{ number_format($uniqueCandidates) }}</div></div></div></div><div class="col-md-8"><div class="card card-sm"><div class="card-body"><div class="text-secondary small">Subject counts are events by subject. A candidate can appear in more than one subject, so the subject totals may be higher than the unique candidate count. Subjects 008 and 009 are shown as one combined rule.</div></div></div></div></div>

    <div class="card mb-3"><div class="card-header"><h3 class="card-title">Paper Crash Statistics by Subject</h3></div><div class="table-responsive"><table class="table table-vcenter card-table mb-0"><thead><tr><th>Subject</th><th class="text-end">Candidates</th></tr></thead><tbody>
        @foreach($statistics as $code => $count)<tr><td>{{ $code === '008_009' ? '008 + 009 combined' : $code }}</td><td class="text-end fw-bold">{{ number_format($count) }}</td></tr>@endforeach
    </tbody></table></div></div>

    <div class="card mb-3"><div class="card-body"><form method="get" class="row g-2"><div class="col-md-3"><label class="form-label">Subject</label><select class="form-select" name="subject"><option value="all">All crashed subjects</option>@foreach($subjects as $code)<option value="{{ $code }}" @selected($filters['subject'] === $code)>{{ $code === '008_009' ? '008 + 009 combined' : $code }}</option>@endforeach</select></div><div class="col-md-3"><label class="form-label">REG / USER</label><input class="form-control" name="search" value="{{ $filters['search'] }}"></div><div class="col-md-auto align-self-end"><button class="btn btn-primary">Apply Filter</button></div><div class="col-md-auto align-self-end"><a class="btn btn-outline-secondary" href="{{ route('written.paper-crashes') }}">Reset</a></div></form></div></div>

    <div class="card"><div class="table-responsive"><table class="table table-vcenter card-table"><thead><tr><th>REG</th><th>USER</th><th>Category</th><th>Qualified Track</th><th>Subject</th><th class="text-end">Actual</th><th class="text-end">Threshold</th><th class="text-end">Counted</th></tr></thead><tbody>
        @forelse($rows as $row)
            @php
                $combined = (string) $row->subject_code === '008';
                $actual = $combined ? (float) $row->actual_mark + (float) ($row->mark009_actual ?? 0) : (float) $row->actual_mark;
                $counted = $combined ? (float) $row->counted_mark + (float) ($row->mark009_counted ?? 0) : (float) $row->counted_mark;
            @endphp
            <tr class="table-warning"><td>{{ $row->reg }}</td><td>{{ $row->user_id }}</td><td>{{ [1=>'1 - GG',2=>'2 - TT',3=>'3 - GT'][(int)$row->cadre_category] ?? $row->cadre_category }}</td><td>{{ $row->written_qualified_track ?: '—' }}</td><td>{{ $combined ? '008 + 009 combined' : $row->subject_code }}</td><td class="text-end">{{ number_format($actual,2) }}</td><td class="text-end">{{ number_format((float)$row->crash_threshold,2) }}</td><td class="text-end">{{ number_format($counted,2) }}</td></tr>
        @empty
            <tr><td colspan="8" class="text-center text-secondary py-4">No paper crash records match the selected filters.</td></tr>
        @endforelse
    </tbody></table></div><div class="card-footer d-flex align-items-center justify-content-between gap-3 flex-wrap"><x-pagination-summary :paginator="$rows" />@if($rows->hasPages()){{ $rows->links() }}@endif</div></div>
</div></div>
@endsection
