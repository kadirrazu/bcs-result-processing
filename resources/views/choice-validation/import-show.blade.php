@extends('layouts.app')
@section('title','Choice Source Import Review')

@section('page-header')
<div class="row g-2 align-items-center">
    <div class="col">
        <h2 class="page-title">Choice Source Import #{{ $batch->id }}</h2>
        <div class="text-secondary">{{ $batch->original_name }} · configured maximum {{ $batch->configured_maximum_choices }} · source version {{ $batch->source_version ?? 'not assigned' }}</div>
    </div>
    <div class="col-auto ms-auto d-flex gap-2 flex-wrap">
        <a class="btn btn-outline-secondary" href="{{ route('choice-validation.index') }}">Back</a>

        @if(in_array($batch->status, ['staged', 'validation_failed'], true))
            <form method="POST" action="{{ route('choice-validation.import.validate', $batch) }}">@csrf
                <button class="btn btn-primary">{{ $batch->status === 'validation_failed' ? 'Retry Validation' : 'Validate Source' }}</button>
            </form>
        @endif

        @if(in_array($batch->status, ['validated', 'partially_approved'], true) && $approvableRows > 0)
            <form method="POST" action="{{ route('choice-validation.import.approve', $batch) }}">@csrf
                <button class="btn btn-success">Approve / Merge {{ number_format($approvableRows) }} Valid Rows</button>
            </form>
        @endif

        @if(in_array($batch->status, ['validated', 'partially_approved'], true) && (int)$batch->invalid_rows > 0)
            <a class="btn btn-outline-warning" href="{{ route('choice-validation.import.invalid-rows', $batch) }}">Download Invalid Rows</a>
        @endif
    </div>
</div>
@endsection

@section('content')
@php
    $activeStatuses = ['queued', 'processing', 'validation_queued', 'validating'];
    $showProgress = in_array($batch->status, $activeStatuses, true);
@endphp

@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

<div id="choice-import-progress" class="card mb-3" data-status-url="{{ route('choice-validation.import.status', $batch) }}" @unless($showProgress) style="display:none" @endunless>
    <div class="card-header">
        <div><h3 class="card-title">{{ in_array($batch->status, ['validation_queued', 'validating'], true) ? 'Source Validation Progress' : 'Choice Staging Progress' }}</h3><div class="text-secondary small" id="choice-progress-step">{{ \App\Support\ChoiceValidationStatusPresenter::label($batch->status) }}</div></div>
        <span class="ms-auto badge bg-blue-lt" id="choice-progress-status">{{ \App\Support\ChoiceValidationStatusPresenter::label($batch->status) }}</span>
    </div>
    <div class="card-body">
        <div class="d-flex justify-content-between mb-2"><span id="choice-progress-count">{{ number_format($batch->processed_rows) }} of {{ number_format($batch->total_rows) }} rows</span><span id="choice-progress-percent">{{ number_format((float)$batch->progress_percent,1) }}%</span></div>
        <div class="progress progress-lg"><div id="choice-progress-bar" class="progress-bar progress-bar-striped progress-bar-animated" style="width: {{ min(100,(float)$batch->progress_percent) }}%"></div></div>
        <div class="text-secondary small mt-2">Staging chunk: {{ number_format((int)config('choice-validation.staging_chunk_size',1000)) }} · Validation chunk: {{ number_format((int)config('choice-validation.validation_chunk_size',1000)) }} · Approval chunk: {{ number_format((int)config('choice-validation.approval_chunk_size',1000)) }}</div>
        <div id="choice-progress-failure" class="alert alert-danger mt-3 mb-0 d-none"></div>
    </div>
</div>

@if(in_array($batch->status,['failed','validation_failed'],true))<div class="alert alert-danger"><strong>{{ $batch->status==='failed' ? 'Staging failed.' : 'Validation failed.' }}</strong><br>{{ $batch->failure_message }}</div>@endif
@if($batch->status==='staged')<div class="alert alert-success"><strong>Staging completed.</strong> {{ number_format($batch->total_rows) }} source rows were preserved. Click <strong>Validate Source</strong>.</div>@endif

@if(in_array($batch->status,['validated','partially_approved'],true) && (int)$batch->invalid_rows>0)
    <div class="alert alert-warning">
        <strong>Row-level invalid data does not block valid-row approval.</strong> You may approve/merge {{ number_format($approvableRows) }} currently valid row(s) now. Download the {{ number_format($batch->invalid_rows) }} invalid row(s), correct only those rows, then re-upload the correction workbook for revalidation.
    </div>
@endif

<div class="row row-cards mb-3">
    <div class="col-md"><div class="card card-sm"><div class="card-body"><div class="text-secondary">Status</div><div class="h3" id="choice-metric-status">{{ \App\Support\ChoiceValidationStatusPresenter::label($batch->status) }}</div></div></div></div>
    <div class="col-md"><div class="card card-sm"><div class="card-body"><div class="text-secondary">Total</div><div class="h3" id="choice-metric-total">{{ number_format($batch->total_rows) }}</div></div></div></div>
    <div class="col-md"><div class="card card-sm"><div class="card-body"><div class="text-secondary">Valid</div><div class="h3" id="choice-metric-valid">{{ number_format($batch->valid_rows) }}</div></div></div></div>
    <div class="col-md"><div class="card card-sm"><div class="card-body"><div class="text-secondary">Invalid</div><div class="h3" id="choice-metric-invalid">{{ number_format($batch->invalid_rows) }}</div></div></div></div>
    <div class="col-md"><div class="card card-sm"><div class="card-body"><div class="text-secondary">Approved / Merged</div><div class="h3">{{ number_format($batch->approved_rows) }}</div></div></div></div>
</div>

@if(in_array($batch->status,['validated','partially_approved'],true) && (int)$batch->invalid_rows>0)
<div class="card mb-3 border-warning">
    <div class="card-header"><div><h3 class="card-title mb-1">Invalid Row Correction</h3><div class="text-secondary small">Only currently invalid rows may be changed through this correction workbook. Previously valid/approved rows are protected.</div></div></div>
    <div class="card-body">
        <div class="row g-3 align-items-end">
            <div class="col-lg-5"><a class="btn btn-outline-warning w-100" href="{{ route('choice-validation.import.invalid-rows',$batch) }}">Download {{ number_format($batch->invalid_rows) }} Invalid Rows</a></div>
            <div class="col-lg-7">
                <form method="POST" action="{{ route('choice-validation.import.correct-invalid',$batch) }}" enctype="multipart/form-data" class="row g-2 align-items-end">@csrf
                    <div class="col-md"><label class="form-label">Corrected invalid-row workbook</label><input class="form-control" type="file" name="correction_file" accept=".xlsx,.csv" required></div>
                    <div class="col-auto"><button class="btn btn-warning">Re-upload &amp; Revalidate</button></div>
                </form>
            </div>
        </div>
        <div class="text-secondary small mt-3">Do not change <code>source_batch_id</code>, <code>source_row</code>, workbook headers or the <code>validation_error</code> column. The error column is informational; the system ignores it during correction merge.</div>
    </div>
</div>
@endif

@if($corrections->isNotEmpty())
<div class="card mb-3">
    <div class="card-header"><h3 class="card-title">Recent Invalid Row Corrections</h3></div>
    <div class="table-responsive"><table class="table table-vcenter card-table"><thead><tr><th>Source row</th><th>Operator</th><th>Correction file</th><th>Time</th></tr></thead><tbody>
        @foreach($corrections as $correction)<tr><td>{{ $correction->source_row }}</td><td>{{ $correction->actor_name ?? $correction->actor_id }}</td><td>{{ $correction->source_filename }}</td><td>{{ $correction->created_at?->format('d-m-Y h:i A') }}</td></tr>@endforeach
    </tbody></table></div>
</div>
@endif

<div class="card mb-3"><div class="card-body"><form class="row g-2"><div class="col-md-4"><input class="form-control" name="search" value="{{ $search }}" placeholder="Search reg or user"></div><div class="col-md-3"><select class="form-select" name="validation"><option value="all">All validation states</option><option value="pending" @selected($validation==='pending')>Pending</option><option value="invalid" @selected($validation==='invalid')>Invalid</option><option value="valid" @selected($validation==='valid')>Valid</option></select></div><div class="col-auto"><button class="btn btn-outline-primary">Filter</button></div></form></div></div>

<div class="card">
    <div class="table-responsive"><table class="table table-vcenter card-table"><thead><tr><th>Row</th><th>User</th><th>Reg</th><th>Raw Choices</th><th>Count</th><th>Status</th><th>Issues</th></tr></thead><tbody>
        @foreach($rows as $row)
            @php($rowStatus = $row->validation_status->value)
            <tr>
                <td>{{ $row->source_row }}</td><td>{{ $row->user_id }}</td><td>{{ $row->reg }}</td>
                <td class="text-wrap" style="min-width:260px">@foreach(($row->raw_choices ?? []) as $column=>$value)@if(filled($value))<span class="badge bg-azure-lt me-1 mb-1">{{ $column }}={{ $value }}</span>@endif @endforeach</td>
                <td>{{ $row->raw_choice_count }}</td>
                <td><span class="badge {{ $rowStatus==='valid' ? 'bg-green-lt text-green' : ($rowStatus==='invalid' ? 'bg-red-lt text-red' : 'bg-secondary-lt text-secondary') }}">{{ strtoupper($rowStatus) }}</span></td>
                <td class="text-wrap">@foreach(($row->validation_errors ?? []) as $error)<div class="text-danger">{{ $error }}</div>@endforeach</td>
            </tr>
        @endforeach
    </tbody></table></div>
    <div class="card-footer">{{ $rows->links() }}</div>
</div>
@endsection

@if($showProgress)
@push('scripts')
<script>
(() => {
    const panel = document.getElementById('choice-import-progress');
    if (!panel) return;
    const fmt = value => Number(value || 0).toLocaleString();
    const poll = async () => {
        try {
            const response = await fetch(panel.dataset.statusUrl, {headers:{Accept:'application/json'}});
            if (!response.ok) { window.setTimeout(poll, 4000); return; }
            const data = await response.json();
            const pct = Math.min(100, Number(data.progress_percent || 0));
            const status = String(data.status || '').replaceAll('_',' ').toUpperCase();
            document.getElementById('choice-progress-step').textContent = status;
            document.getElementById('choice-progress-status').textContent = status;
            document.getElementById('choice-progress-count').textContent = `${fmt(data.processed_rows)} of ${fmt(data.total_rows)} rows`;
            document.getElementById('choice-progress-percent').textContent = `${pct.toFixed(1)}%`;
            document.getElementById('choice-progress-bar').style.width = `${pct}%`;
            document.getElementById('choice-metric-status').textContent = status;
            document.getElementById('choice-metric-total').textContent = fmt(data.total_rows);
            document.getElementById('choice-metric-valid').textContent = fmt(data.valid_rows);
            document.getElementById('choice-metric-invalid').textContent = fmt(data.invalid_rows);
            if (data.failure_message) { const failure=document.getElementById('choice-progress-failure'); failure.textContent=data.failure_message; failure.classList.remove('d-none'); }
            if (data.finished) { window.setTimeout(() => window.location.reload(), 650); return; }
            window.setTimeout(poll,1500);
        } catch (_) { window.setTimeout(poll,4000); }
    };
    window.setTimeout(poll,700);
})();
</script>
@endpush
@endif
