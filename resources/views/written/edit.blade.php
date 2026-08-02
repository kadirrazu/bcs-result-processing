@extends('layouts.app')
@section('title', 'Edit Written Result')
@section('content')
<div class="page-header d-print-none">
    <div class="container-xl">
        <div class="row g-2 align-items-center">
            <div class="col">
                <h2 class="page-title">Edit Written Result</h2>
                <div class="text-secondary">Mark, PRS code, Written status and operator comment are audited. data_source_note is read-only source context.</div>
            </div>
            <div class="col-auto ms-auto d-flex gap-2">
                <a href="{{ route('written.results.show', $result) }}" class="btn btn-outline-secondary">Candidate View</a>
                <a href="{{ route('written.results') }}" class="btn btn-outline-secondary">Back to Results</a>
            </div>
        </div>
    </div>
</div>

<div class="page-body">
    <div class="container-xl">
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @php
            $category = (int) ($registration?->cadre_category ?? $result->cadre_category ?? 0);
            $currentStatus = $result->status instanceof \BackedEnum ? $result->status->value : (string) $result->status;
            $markMap = $result->marks->keyBy('subject_code');
        @endphp

        <div class="alert alert-info">
            <strong>Stale policy:</strong> changing any mark, PRS code or Written status invalidates reconciliation and rule-processing output. A comment-only edit is audited but does not make processing stale.
        </div>

        <div class="row row-cards">
            <div class="col-xl-8">
                <div class="card mb-3">
                    <div class="card-header"><h3 class="card-title">Candidate &amp; Source Context</h3></div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-md-3">REG</dt><dd class="col-md-9">{{ $result->reg }}</dd>
                            <dt class="col-md-3">USER</dt><dd class="col-md-9">{{ $result->user_id }}</dd>
                            <dt class="col-md-3">Name</dt><dd class="col-md-9">{{ $registration?->name ?? '—' }}</dd>
                            <dt class="col-md-3">Original Category</dt><dd class="col-md-9"><span class="badge bg-blue-lt">{{ $display['cadre_category'] }}</span></dd>
                            <dt class="col-md-3">Registration PRS</dt><dd class="col-md-9"><strong>{{ $display['registration_prs'] }}</strong></dd>
                            <dt class="col-md-3">Written Imported PRS</dt><dd class="col-md-9"><strong>{{ $display['written_prs'] }}</strong> @if($display['prs_mismatch'])<span class="badge bg-yellow-lt ms-1">Mismatch</span>@endif</dd>
                            <dt class="col-md-3">Bachelor Subject</dt><dd class="col-md-9">{{ $display['bachelor_subject'] }}</dd>
                            <dt class="col-md-3">District</dt><dd class="col-md-9">{{ $display['district'] }}</dd>
                            <dt class="col-md-3">data_source_note</dt><dd class="col-md-9"><div class="form-control-plaintext">{{ $result->data_source_note ?: '—' }}</div><div class="form-hint">Read-only. This preserves imported source context and never drives Written status.</div></dd>
                        </dl>
                    </div>
                </div>

                <form method="post" action="{{ route('written.results.update', $result) }}">
                    @csrf
                    @method('PUT')

                    <div class="card mb-3">
                        <div class="card-header"><h3 class="card-title">Subject Marks</h3><div class="card-actions text-secondary small">Numeric, ABS or AAA · AAA is interpreted as absence</div></div>
                        <div class="card-body">
                            <div class="row g-3">
                                @foreach($subjectConfig as $subjectCode => $meta)
                                    @php
                                        $mark = $markMap->get($subjectCode);
                                        $defaultValue = $mark?->raw_value;
                                        if (($defaultValue === null || $defaultValue === '') && $mark?->attendance_status === 'absent') {
                                            $defaultValue = 'ABS';
                                        } elseif (($defaultValue === null || $defaultValue === '') && $mark?->actual_mark !== null) {
                                            $defaultValue = rtrim(rtrim(number_format((float) $mark->actual_mark, 2, '.', ''), '0'), '.');
                                        }
                                    @endphp
                                    <div class="col-sm-6 col-md-4">
                                        <label class="form-label">{{ $subjectCode }} <span class="text-secondary">/ {{ number_format((float) $meta['full_mark'], 0) }}</span></label>
                                        <input type="text" name="marks[{{ $subjectCode }}]" class="form-control @error('marks.'.$subjectCode) is-invalid @enderror" value="{{ old('marks.'.$subjectCode, $defaultValue) }}">
                                        @error('marks.'.$subjectCode)<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        @if($mark?->paper_crashed)
                                            <div class="form-hint text-warning">Previous processing: paper crashed; counted {{ number_format((float) $mark->counted_mark, 2) }}</div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <div class="card mb-3">
                        <div class="card-header"><h3 class="card-title">Written Processing Facts</h3></div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Written Imported PRS Code</label>
                                    <input type="text" name="prs_code" class="form-control @error('prs_code') is-invalid @enderror" value="{{ old('prs_code', $result->prs_code) }}">
                                    @error('prs_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    <div class="form-hint">Current: {{ $display['written_prs'] }} · Registration expects: {{ $display['registration_prs'] }}</div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Written Status <span class="text-danger">*</span></label>
                                    <select name="status" class="form-select" required>
                                        @foreach($statusOptions as $statusOption)
                                            <option value="{{ $statusOption }}" @selected(old('status', $currentStatus) === $statusOption)>{{ strtoupper($statusOption) }}</option>
                                        @endforeach
                                    </select>
                                    <div class="form-hint">Only Active candidates are included in Written rule processing. Cancelled, Withheld and Expelled candidates remain on record but stay outside the processing pipeline.</div>
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label">Operator Comment</label>
                                    <textarea name="comment" rows="3" class="form-control" placeholder="Optional internal Written processing note">{{ old('comment', $result->comment) }}</textarea>
                                    <div class="form-hint">Comment-only edits are audited but do not invalidate processing.</div>
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label">Reason for Change <span class="text-danger">*</span></label>
                                    <textarea name="reason" rows="3" class="form-control" required>{{ old('reason') }}</textarea>
                                    <div class="form-hint">Mandatory. Stored permanently in examination DB audit and Written daily file log.</div>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer text-end"><button class="btn btn-primary">Save with Audit Trail</button></div>
                    </div>
                </form>
            </div>

            <div class="col-xl-4">
                <div class="card">
                    <div class="card-header"><h3 class="card-title">Manual Edit History</h3></div>
                    <div class="table-responsive">
                        <table class="table table-sm card-table">
                            <thead><tr><th>Time</th><th>Actor</th><th>Reason</th></tr></thead>
                            <tbody>
                                @forelse($audits as $audit)
                                    <tr>
                                        <td>{{ $audit->created_at?->format('d-m-Y h:i A') ?? '—' }}</td>
                                        <td>{{ $audit->actor_name ?: $audit->actor_id }}</td>
                                        <td>{{ $audit->reason ?: '—' }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="text-center text-secondary py-3">No manual edits yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
