@extends('layouts.app')
@section('content')
<div class="page-header"><div class="container-xl"><div class="row align-items-center"><div class="col"><h2 class="page-title">A6 — Fill Final Allocation DOCX Template</h2><div class="text-secondary">Original template remains unchanged; a new document is generated.</div></div><div class="col-auto ms-auto"><a class="btn btn-outline-secondary" href="{{ route('allocation.a6.index') }}">Back to A6</a></div></div></div></div>
<div class="page-body"><div class="container-xl"><div class="card"><div class="card-body"><form method="POST" action="{{ route('allocation.a6.docx.generate') }}" enctype="multipart/form-data">@csrf
<div class="mb-3"><label class="form-label">Word template (.docx)</label><input class="form-control" type="file" name="template_file" accept=".docx" required></div>
<div class="row g-3"><div class="col-md-4"><label class="form-label">Result Date</label><input class="form-control" type="date" name="result_date" value="{{ old('result_date', now()->format('Y-m-d')) }}" required></div><div class="col-md-4"><label class="form-label">Registrations per Line</label><input class="form-control" type="number" name="registrations_per_line" min="1" max="20" value="{{ old('registrations_per_line',$defaultPerLine) }}" required></div></div>
<div class="alert alert-info mt-4"><div class="fw-bold mb-2">Supported placeholders</div><code>[[110_ADMN]]</code> · <code>[[TOTAL_110_ADMN]]</code> · <code>[[ALL_ALLOCATED]]</code> · <code>[[TOTAL_ALLOCATED]]</code> · <code>[[EXAM_NAME]]</code> · <code>[[RESULT_DATE]]</code> · <code>[[A5_FINALIZED_DATE]]</code> · <code>[REPORT_GENERATION_TIMESTAMP]</code></div>
<button class="btn btn-primary">Generate DOCX</button></form></div></div></div></div>
@endsection
