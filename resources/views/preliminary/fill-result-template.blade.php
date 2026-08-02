@extends('layouts.app')

@section('title', 'Fill Preliminary Result Template')

@section('content')
<div class="page-header">
    <div class="container-xl">
        <div class="row g-2 align-items-center">
            <div class="col">
                <h2 class="page-title">Fill Preliminary Result Template</h2>
                <div class="text-secondary">Use an existing Word notice or publishing template and place the finalized Preliminary result into its placeholders.</div>
            </div>
            <div class="col-auto ms-auto">
                <a class="btn btn-outline-secondary" href="{{ route('preliminary.final-result.category') }}">Back to Final Result</a>
            </div>
        </div>
    </div>
</div>

<div class="page-body">
    <div class="container-xl">
        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <div class="row row-cards">
            <div class="col-lg-7">
                <div class="card">
                    <div class="card-header"><h3 class="card-title">Create publishing document</h3></div>
                    <div class="card-body">
                        <form method="post" action="{{ route('preliminary.final-result.template.generate') }}" enctype="multipart/form-data">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label">Word template (.docx)</label>
                                <input type="file" class="form-control" name="template_file" accept=".docx" required>
                                @error('template_file')<div class="text-danger mt-1">{{ $message }}</div>@enderror
                                <div class="form-hint">The original template is left unchanged. A new Word document will be created for download.</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Registration numbers per line</label>
                                <input type="number" class="form-control" name="registrations_per_line" min="1" max="12" value="{{ old('registrations_per_line', $defaultPerLine) }}">
                                @error('registrations_per_line')<div class="text-danger mt-1">{{ $message }}</div>@enderror
                                <div class="form-hint">Choose the layout that suits the notice. Eight registration numbers per line works well for most templates.</div>
                            </div>
                            <button class="btn btn-primary">Create Publishing Document</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card">
                    <div class="card-header"><h3 class="card-title">Available placeholders</h3></div>
                    <div class="table-responsive">
                        <table class="table table-sm card-table">
                            <tbody>
                                <tr><td><code>[[GG]]</code></td><td>GG result block, including total</td></tr>
                                <tr><td><code>[[TT]]</code></td><td>TT result block, including total</td></tr>
                                <tr><td><code>[[GT]]</code></td><td>GT result block, including total</td></tr>
                                <tr><td><code>[[ALL]]</code></td><td>Combined result block, including total</td></tr>
                                <tr><td><code>[[RESULT_GG]]</code></td><td>GG registration numbers only</td></tr>
                                <tr><td><code>[[RESULT_TT]]</code></td><td>TT registration numbers only</td></tr>
                                <tr><td><code>[[RESULT_GT]]</code></td><td>GT registration numbers only</td></tr>
                                <tr><td><code>[[RESULT_ALL]]</code></td><td>All passed registration numbers</td></tr>
                                <tr><td><code>[[TOTAL_GG]]</code>, <code>[[TOTAL_TT]]</code>, <code>[[TOTAL_GT]]</code>, <code>[[TOTAL_ALL]]</code></td><td>Numeric totals</td></tr>
                                <tr><td><code>[[CUTOFF_MARK]]</code></td><td>Approved Preliminary cut-off mark</td></tr>
                                <tr><td><code>[[EXAM_NAME]]</code></td><td>Selected examination name</td></tr>
                                <tr><td><code>[[FINALIZED_DATE]]</code></td><td>Preliminary result finalization date</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="alert alert-info mt-3 mb-0">For the most predictable formatting, keep each result placeholder on its own line. Placeholders also work in Word table cells, headers and footers.</div>
    </div>
</div>
@endsection
