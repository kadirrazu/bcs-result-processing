@extends('layouts.app')
@section('title','Written High-mark Review')
@section('content')
<div class="page-header d-print-none">
    <div class="container-xl"><div class="row g-2 align-items-center">
        <div class="col">
            <h2 class="page-title">Written High-mark Review</h2>
            <div class="text-secondary">Candidates meeting or exceeding the configured {{ number_format($threshold, 2) }}% review threshold. This is a review signal, not a failure condition.</div>
        </div>
        <div class="col-auto d-flex gap-2">
            <a class="btn btn-outline-secondary" href="{{ route('written.index') }}">Back to Written</a>
            <a class="btn btn-outline-success" href="{{ route('written.high-marks.xlsx', request()->query()) }}">Export XLSX</a>
            <a class="btn btn-outline-secondary" href="{{ route('written.high-marks.csv', request()->query()) }}">Export CSV</a>
        </div>
    </div></div>
</div>
<div class="page-body"><div class="container-xl">
    <div class="row row-cards mb-3">
        <div class="col-md-4"><div class="card card-sm border-azure"><div class="card-body"><div class="text-secondary">Candidates flagged for High-mark Review</div><div class="h2 text-azure mb-0">{{ number_format($uniqueCandidates) }}</div></div></div></div>
        <div class="col-md-8"><div class="card card-sm"><div class="card-body"><div class="text-secondary small">A candidate may be flagged in more than one subject. Subject 008 + 009 is reviewed as one combined 100-mark paper for this threshold.</div></div></div></div>
    </div>

    <div class="card mb-3">
        <div class="card-header"><h3 class="card-title">High-mark Statistics by Subject</h3><div class="card-actions text-secondary small">Threshold: {{ number_format($threshold,2) }}% or above</div></div>
        <div class="table-responsive"><table class="table table-vcenter card-table mb-0"><thead><tr><th>Subject</th><th class="text-end">Candidates</th></tr></thead><tbody>
            @foreach($statistics as $code => $count)
                <tr><td>{{ $code === '008_009' ? '008 + 009 combined' : $code }}</td><td class="text-end fw-bold">{{ number_format($count) }}</td></tr>
            @endforeach
        </tbody></table></div>
    </div>

    <div class="card mb-3"><div class="card-body"><form method="get" class="row g-2">
        <div class="col-md-3"><label class="form-label">Subject</label><select class="form-select" name="subject"><option value="all">All reviewed subjects</option>@foreach($subjects as $code)<option value="{{ $code }}" @selected($filters['subject'] === $code)>{{ $code === '008_009' ? '008 + 009 combined' : $code }}</option>@endforeach</select></div>
        <div class="col-md-3"><label class="form-label">REG / USER</label><input class="form-control" name="search" value="{{ $filters['search'] }}"></div>
        <div class="col-md-auto align-self-end"><button class="btn btn-primary">Apply Filter</button></div>
        <div class="col-md-auto align-self-end"><a class="btn btn-outline-secondary" href="{{ route('written.high-marks') }}">Reset</a></div>
    </form></div></div>

    <div class="card"><div class="table-responsive"><table class="table table-vcenter card-table"><thead><tr><th>REG</th><th>USER</th><th>Category</th><th>Qualified Track</th><th>Subject</th><th class="text-end">Actual</th><th class="text-end">Full Mark</th><th class="text-end">Percentage</th></tr></thead><tbody>
        @forelse($rows as $row)
            @php
                $combined = (string) $row->subject_code === '008';
                $actual = $combined
                    ? (float) $row->actual_mark + (float) ($row->mark009_actual ?? 0)
                    : (float) $row->actual_mark;
                $full = $combined
                    ? (float) data_get(config('written.subjects'), '008.full_mark', 50) + (float) data_get(config('written.subjects'), '009.full_mark', 50)
                    : (float) data_get(config('written.subjects'), $row->subject_code.'.full_mark', 0);
                $percent = $full > 0 ? ($actual / $full) * 100 : 0;
            @endphp
            <tr class="table-warning">
                <td>{{ $row->reg }}</td><td>{{ $row->user_id }}</td><td>{{ [1=>'1 - GG',2=>'2 - TT',3=>'3 - GT'][(int)$row->cadre_category] ?? $row->cadre_category }}</td><td>{{ $row->written_qualified_track ?: '—' }}</td><td>{{ $combined ? '008 + 009 combined' : $row->subject_code }}</td><td class="text-end">{{ number_format($actual,2) }}</td><td class="text-end">{{ number_format($full,2) }}</td><td class="text-end fw-semibold">{{ number_format($percent,2) }}%</td>
            </tr>
        @empty
            <tr><td colspan="8" class="text-center text-secondary py-4">No high-mark review rows match the selected filters.</td></tr>
        @endforelse
    </tbody></table></div>
    <div class="card-footer d-flex align-items-center justify-content-between gap-3 flex-wrap"><x-pagination-summary :paginator="$rows" />@if($rows->hasPages()){{ $rows->links() }}@endif</div></div>
</div></div>
@endsection
