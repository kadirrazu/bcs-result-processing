@extends('layouts.app')
@section('title','Choice Validation')

@section('page-header')
<div class="row g-2 align-items-center">
    <div class="col">
        <h2 class="page-title">Choice Validation</h2>
        <div class="text-secondary">Source Import & Validation → Source Approval & Correction → Choice Validation & Review → Finalization</div>
    </div>
    <div class="col-auto ms-auto d-flex gap-2 flex-wrap">
        <a class="btn btn-outline-primary" href="{{ route('choice-validation.template') }}">Download Excel Template</a>
        @if($latestBatch)
            <a class="btn btn-outline-secondary" href="{{ route('choice-validation.import.show', $latestBatch) }}">Review Latest Batch</a>
        @endif
    </div>
</div>
@endsection

@section('content')
@php
    $batchStatus = $latestBatch?->status ?? 'not_started';
    $runStatus = $latestValidationRun?->status ?? 'not_started';
    $approvedRows = $latestBatch ? (int) $latestBatch->approved_rows : 0;
    $invalidRows = $latestBatch ? (int) $latestBatch->invalid_rows : 0;
    $approvableRows = $latestBatch ? max(0, (int) $latestBatch->valid_rows - $approvedRows) : 0;
    $sourcePartiallyApproved = $approvedRows > 0 && $invalidRows > 0;
    $sourceFullyApproved = $latestBatch && $latestBatch->status === 'approved' && $invalidRows === 0;
@endphp

@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

<div class="row row-cards mb-3 align-items-stretch">
    <div class="col-sm-6 col-lg-3 d-flex"><div class="card card-sm h-100 w-100"><div class="card-body"><div class="text-secondary">Maximum Allowed Choices</div><div class="h2 mb-0">{{ $maximumAllowedChoices }}</div><div class="small text-secondary">opt_01 to opt_{{ str_pad($maximumAllowedChoices,2,'0',STR_PAD_LEFT) }}</div></div></div></div>
    <div class="col-sm-6 col-lg-3 d-flex"><div class="card card-sm h-100 w-100"><div class="card-body"><div class="text-secondary">Approved Source Rows</div><div class="h2 mb-0">{{ number_format($sourceCount) }}</div><div class="small text-secondary">Source v{{ $state->approved_source_version ?? '—' }}</div></div></div></div>
    <div class="col-sm-6 col-lg-3 d-flex"><div class="card card-sm h-100 w-100"><div class="card-body"><div class="text-secondary">Pending Invalid Correction</div><div class="h2 mb-0">{{ number_format($pendingCorrectionRows) }}</div><div class="small text-secondary">Does not block valid-row approval</div></div></div></div>
    <div class="col-sm-6 col-lg-3 d-flex"><div class="card card-sm h-100 w-100"><div class="card-body"><div class="text-secondary">Module State</div><div class="h3 mb-0">{{ \App\Support\ChoiceValidationStatusPresenter::label($state->status) }}</div>@if($state->is_stale)<div class="small text-warning">Choice Validation is outdated</div>@endif</div></div></div>
</div>

<div class="card mb-3 shadow-sm">
    <div class="card-header">
        <div>
            <h3 class="card-title mb-1">Processing Status Board</h3>
            <div class="text-secondary small">Only operationally useful stages and actions are shown.</div>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-vcenter card-table mb-0">
            <thead><tr><th>Stage</th><th>Status</th><th>Action / Summary</th></tr></thead>
            <tbody>
                <tr>
                    <td class="fw-medium">1. Source Import &amp; Validation</td>
                    <td>
                        @if(!$latestBatch)
                            <span class="badge bg-secondary-lt text-secondary">Pending</span>
                        @elseif(in_array($latestBatch->status,['validation_queued','validating'],true))
                            <span class="badge bg-blue-lt text-blue">{{ \App\Support\ChoiceValidationStatusPresenter::label($latestBatch->status) }}</span>
                        @elseif(in_array($latestBatch->status,['validated','partially_approved','approved'],true))
                            <span class="badge bg-green-lt text-green">Completed</span>
                        @elseif($latestBatch->status==='staged')
                            <span class="badge bg-teal-lt text-teal">Ready to Validate</span>
                        @else
                            <span class="badge {{ \App\Support\ChoiceValidationStatusPresenter::badgeClass($batchStatus) }}">{{ \App\Support\ChoiceValidationStatusPresenter::label($batchStatus) }}</span>
                        @endif
                    </td>
                    <td>
                        @if($latestBatch)<a class="btn btn-sm btn-outline-primary" href="{{ route('choice-validation.import.show',$latestBatch) }}">Review Source Batch</a>
                        @else<span class="text-secondary">Upload a Choice source file below.</span>@endif
                    </td>
                </tr>

                <tr>
                    <td class="fw-medium">2. Source Approval &amp; Correction</td>
                    <td>
                        @if($sourceFullyApproved)
                            <span class="badge bg-green-lt text-green">Complete</span>
                        @elseif($sourcePartiallyApproved || ($latestBatch && $invalidRows>0))
                            <span class="badge bg-yellow-lt text-yellow">Needs Correction</span>
                        @elseif($latestBatch && $approvableRows>0)
                            <span class="badge bg-teal-lt text-teal">Ready</span>
                        @else
                            <span class="badge bg-secondary-lt text-secondary">Pending</span>
                        @endif
                    </td>
                    <td>
                        <div class="d-flex gap-2 flex-wrap align-items-center">
                            @if($latestBatch && $approvableRows>0)
                                <a class="btn btn-sm btn-outline-success" href="{{ route('choice-validation.import.show',$latestBatch) }}">Approve / Merge {{ number_format($approvableRows) }} Valid</a>
                            @endif
                            @if($latestBatch && $invalidRows>0)
                                <a class="btn btn-sm btn-outline-warning" href="{{ route('choice-validation.import.invalid-rows',$latestBatch) }}">Download {{ number_format($invalidRows) }} Invalid</a>
                                <a class="btn btn-sm btn-outline-primary" href="{{ route('choice-validation.import.show',$latestBatch) }}">Correct &amp; Re-upload</a>
                            @endif
                            @if($latestBatch && $approvedRows>0)
                                <span class="small text-secondary">{{ number_format($approvedRows) }} approved</span>
                            @elseif(!$latestBatch)
                                <span class="text-secondary">Source batch required.</span>
                            @endif
                        </div>
                    </td>
                </tr>

                <tr>
                    <td class="fw-medium">3. Choice Validation &amp; Review</td>
                    <td>
                        @if($latestValidationRun)
                            <span class="badge {{ \App\Support\ChoiceValidationStatusPresenter::badgeClass($runStatus) }}">{{ \App\Support\ChoiceValidationStatusPresenter::label($runStatus) }}</span>
                        @elseif($state->approved_source_version)
                            <span class="badge bg-teal-lt text-teal">Ready</span>
                        @else
                            <span class="badge bg-secondary-lt text-secondary">Pending</span>
                        @endif
                    </td>
                    <td>
                        <div class="d-flex gap-2 flex-wrap">
                            @if($state->approved_source_version)
                                <form method="POST" action="{{ route('choice-validation.process') }}">@csrf
                                    <button class="btn btn-sm btn-primary" @disabled($latestValidationRun && in_array($latestValidationRun->status,['queued','running'],true))>
                                        {{ $state->is_stale ? 'Re-run Validation' : 'Run Validation' }}
                                    </button>
                                </form>
                            @endif
                            @if($latestValidationRun)
                                <a class="btn btn-sm btn-outline-primary" href="{{ route('choice-validation.results',$latestValidationRun) }}">Review Results</a>
                            @endif
                            @if(!$state->approved_source_version)<span class="text-secondary">Approve valid source rows first.</span>@endif
                        </div>
                    </td>
                </tr>

                <tr>
                    <td class="fw-medium">4. Finalization</td>
                    <td>
                        @if((int)($state->finalized_validation_version ?? 0) > 0
                            && (int)$state->finalized_validation_version === (int)$state->current_validation_version
                            && !$state->is_stale)
                            <span class="badge bg-green-lt text-green">Finalized</span>
                        @elseif($finalizationReadiness['ready'])
                            <span class="badge bg-teal-lt text-teal">Ready</span>
                        @else
                            <span class="badge bg-secondary-lt text-secondary">Pending / Blocked</span>
                        @endif
                    </td>
                    <td>
                        <div class="d-flex gap-2 flex-wrap align-items-center">
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('choice-validation.finalization.index') }}">
                                Review Finalization
                            </a>
                            @if((int)($state->finalized_validation_version ?? 0) > 0
                                && (int)$state->finalized_validation_version === (int)$state->current_validation_version
                                && !$state->is_stale)
                                <a class="btn btn-sm btn-outline-success" href="{{ route('choice-validation.final-report.index') }}">
                                    Final Report
                                </a>
                            @elseif(!$finalizationReadiness['ready'])
                                <span class="small text-secondary">
                                    {{ $finalizationReadiness['reasons'][0] ?? 'Complete validation review first.' }}
                                </span>
                            @endif
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

@if($sourcePartiallyApproved)
<div class="alert alert-warning mb-3">
    <strong>Partial source approval is active.</strong> {{ number_format($approvedRows) }} valid row(s) are already approved/merged, while {{ number_format($invalidRows) }} invalid row(s) remain in the correction loop. The approved rows are usable and are not blocked by those invalid rows.
</div>
@endif

<div class="card mb-3">
    <div class="card-header">
        <div>
            <h3 class="card-title mb-1">Choice Source Excel Import</h3>
            <div class="card-subtitle">Upload the candidate Choice source exactly in the configured column contract.</div>
        </div>
    </div>
    <div class="card-body">
        <div class="bg-light rounded border p-3 mb-3">
            <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
                <span class="badge bg-azure-lt text-azure">Identity: user, reg</span>
                <span class="badge bg-blue-lt text-blue">Choices: opt_01 ... opt_{{ str_pad($maximumAllowedChoices,2,'0',STR_PAD_LEFT) }}</span>
                <span class="badge bg-green-lt text-green">Minimum: 1 choice</span>
                <span class="badge bg-purple-lt text-purple">Maximum: {{ $maximumAllowedChoices }}</span>
            </div>
            <div class="text-secondary small">
                The expected Choice columns are generated from configuration. A header beyond the configured maximum is a file-level blocking
                <code>CHOICE_EXCEEDS_MAXIMUM_ALLOWED_LIMIT</code> error. Row-level invalid data can be corrected separately after validation.
            </div>
        </div>

        <form method="POST" action="{{ route('choice-validation.import.upload') }}" enctype="multipart/form-data">
            @csrf
            <label class="form-label required">Choice source file</label>
            <div class="d-flex flex-column flex-lg-row gap-2 align-items-stretch">
                <input class="form-control flex-fill" type="file" name="file" accept=".xlsx,.csv" required>
                <button class="btn btn-primary flex-shrink-0 px-4">Upload &amp; Stage Source</button>
            </div>
            <div class="form-hint mt-2">Accepted formats: XLSX or CSV. Download the current template from the page header before preparing a new source file.</div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3 class="card-title">Import Batches</h3></div>
    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead><tr><th>#</th><th>File</th><th>Status</th><th>Rows</th><th>Valid</th><th>Invalid</th><th>Approved</th><th>Source v</th><th></th></tr></thead>
            <tbody>
            @forelse($batches as $batch)
                <tr>
                    <td>{{ $batch->id }}</td><td>{{ $batch->original_name }}</td>
                    <td><span class="badge {{ \App\Support\ChoiceValidationStatusPresenter::badgeClass($batch->status) }}">{{ \App\Support\ChoiceValidationStatusPresenter::label($batch->status) }}</span></td>
                    <td>{{ number_format($batch->total_rows) }}</td><td>{{ number_format($batch->valid_rows) }}</td><td>{{ number_format($batch->invalid_rows) }}</td><td>{{ number_format($batch->approved_rows) }}</td><td>{{ $batch->source_version ?? '—' }}</td>
                    <td><a class="btn btn-sm btn-outline-primary" href="{{ route('choice-validation.import.show',$batch) }}">Review</a></td>
                </tr>
            @empty<tr><td colspan="9" class="text-center text-secondary py-4">No Choice source imports yet.</td></tr>@endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $batches->links() }}</div>
</div>
@endsection
