@extends('layouts.app')
@section('title','Choice Validation Results')

@section('page-header')
<div class="row g-2 align-items-center">
    <div class="col">
        <h2 class="page-title">Choice Validation Results</h2>
        <div class="text-secondary">Run #{{ $run->id }} · Source v{{ $run->source_version }} · Circular v{{ $run->circular_version }} · Validation v{{ $run->validation_version }}</div>
    </div>
    <div class="col-auto ms-auto"><a href="{{ route('choice-validation.index') }}" class="btn btn-outline-secondary">Back</a></div>
</div>
@endsection

@section('content')
@if(in_array($run->status,['queued','running'],true))
<div class="card mb-3" id="choice-validation-progress-card"
     data-url="{{ route('choice-validation.runs.progress',$run) }}">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <div>
                <div class="fw-semibold" id="cv-progress-title">Choice Validation is {{ $run->status }}</div>
                <div class="text-secondary small" id="cv-progress-text">Processed {{ number_format($run->processed_candidates) }} / {{ number_format($run->total_candidates) }}</div>
            </div>
            <div class="h3 mb-0" id="cv-progress-percent">{{ number_format($run->progress_percent,1) }}%</div>
        </div>
        <div class="progress progress-lg">
            <div class="progress-bar progress-bar-striped progress-bar-animated" id="cv-progress-bar"
                 style="width: {{ max(0,min(100,(float)$run->progress_percent)) }}%"
                 role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ (float)$run->progress_percent }}"></div>
        </div>
        <div class="row g-2 mt-2 small text-secondary">
            <div class="col-md-3">Valid: <strong id="cv-valid">{{ number_format($run->valid_candidates) }}</strong></div>
            <div class="col-md-3">Not Applicable: <strong id="cv-na">{{ number_format($run->not_applicable_candidates) }}</strong></div>
            <div class="col-md-3">Kept: <strong id="cv-kept">{{ number_format($run->kept_choices) }}</strong></div>
            <div class="col-md-3">Removed: <strong id="cv-removed">{{ number_format($run->removed_choices) }}</strong></div>
        </div>
    </div>
</div>
@endif

<div class="row row-cards mb-3 align-items-stretch">
    <div class="col-sm-3 d-flex"><div class="card card-sm h-100 w-100"><div class="card-body"><div class="text-secondary">Valid</div><div class="h2">{{ number_format($run->valid_candidates) }}</div></div></div></div>
    <div class="col-sm-3 d-flex">
        <div class="card card-sm h-100 w-100">
            <div class="card-body">
                <div class="text-secondary">Not Applicable</div>
                <div class="h2 mb-1">{{ number_format($run->not_applicable_candidates) }}</div>
                @if($notApplicableBreakdown->isNotEmpty())
                    <div class="small text-secondary">
                        @foreach($notApplicableBreakdown as $naStatus => $count)
                            <div>{{ \App\Support\ChoiceValidationStatusPresenter::resultLabel($naStatus) }}: <strong>{{ number_format($count) }}</strong></div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
    <div class="col-sm-3 d-flex"><div class="card card-sm h-100 w-100"><div class="card-body"><div class="text-secondary">No Valid Choice</div><div class="h2">{{ number_format($run->zero_valid_choice_candidates) }}</div></div></div></div>
    <div class="col-sm-3 d-flex"><div class="card card-sm h-100 w-100"><div class="card-body"><div class="text-secondary">Kept / Removed / Expanded</div><div class="h3">{{ $run->kept_choices }} / {{ $run->removed_choices }} / {{ $run->expanded_choices }}</div></div></div></div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-2">
            <div class="col-md-4"><input class="form-control" name="search" value="{{ $search }}" placeholder="Reg or User"></div>
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="all">All status</option>
                    @foreach($statusOptions as $statusOption)
                        <option value="{{ $statusOption }}" @selected($status===$statusOption)>{{ \App\Support\ChoiceValidationStatusPresenter::resultLabel($statusOption) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <select class="form-select" name="reason">
                    <option value="">All reason codes</option>
                    @foreach($reasonOptions as $reasonCode)<option value="{{ $reasonCode }}" @selected($reason===$reasonCode)>{{ $reasonCode }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-2"><button class="btn btn-primary w-100">Filter</button></div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead><tr><th>Candidate</th><th>Track</th><th>Status</th><th>Original</th><th>Validated</th><th>Removed</th><th>Expanded</th><th></th></tr></thead>
            <tbody>
            @forelse($rows as $row)
                <tr>
                    <td>
                        <div class="fw-semibold">{{ $row->registration?->name ?: '—' }}</div>
                        <div class="small">Reg: {{ $row->reg }}</div>
                        <div class="small text-secondary">User: {{ $row->user_id }}</div>
                    </td>
                    <td>{{ strtoupper($row->effective_track ?? '—') }}<div class="small text-secondary">{{ $row->written_qualified_track }}</div></td>
                    <td>
                        <span class="badge {{ \App\Support\ChoiceValidationStatusPresenter::resultBadgeClass($row->status) }}">
                            {{ \App\Support\ChoiceValidationStatusPresenter::resultLabel($row->status) }}
                        </span>
                        @if($row->result_reason_code)
                            <div class="small text-secondary mt-1">{{ $row->result_reason_code }}</div>
                        @endif
                    </td>
                    <td>{{ $row->original_choice_count }}</td>
                    <td><code>{{ implode(' ',(array)$row->validated_choice_codes) }}</code></td>
                    <td>{{ $row->removed_choice_count }}</td>
                    <td>{{ $row->expanded_choice_count }}</td>
                    <td><a class="btn btn-sm btn-outline-primary" href="{{ route('choice-validation.result.detail',$row) }}">Details</a></td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-secondary py-4">No processed rows yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $rows->links() }}</div>
</div>
@endsection

@if(in_array($run->status,['queued','running'],true))
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const card = document.getElementById('choice-validation-progress-card');
    if (!card) return;
    const url = card.dataset.url;
    let reloading = false;
    const number = (value) => new Intl.NumberFormat().format(value ?? 0);
    const poll = async () => {
        try {
            const response = await fetch(url, {headers: {'Accept': 'application/json'}, cache: 'no-store'});
            if (!response.ok) return;
            const data = await response.json();
            const pct = Math.max(0, Math.min(100, Number(data.percent || 0)));
            document.getElementById('cv-progress-title').textContent = 'Choice Validation is ' + String(data.status).replaceAll('_',' ');
            document.getElementById('cv-progress-text').textContent = `Processed ${number(data.processed)} / ${number(data.total)}`;
            document.getElementById('cv-progress-percent').textContent = pct.toFixed(1) + '%';
            const bar = document.getElementById('cv-progress-bar');
            bar.style.width = pct + '%';
            bar.setAttribute('aria-valuenow', pct);
            document.getElementById('cv-valid').textContent = number(data.valid);
            document.getElementById('cv-na').textContent = number(data.not_applicable);
            document.getElementById('cv-kept').textContent = number(data.kept);
            document.getElementById('cv-removed').textContent = number(data.removed);
            if (data.terminal && !reloading) {
                reloading = true;
                window.setTimeout(() => window.location.reload(), 500);
            }
        } catch (_) {}
    };
    poll();
    window.setInterval(poll, 1500);
});
</script>
@endpush
@endif
