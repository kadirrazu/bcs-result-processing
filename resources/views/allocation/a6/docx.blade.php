@extends('layouts.app')
@section('content')
<div class="page-header d-print-none">
    <div class="container-xl">
        <div class="row g-2 align-items-center">
            <div class="col">
                <h2 class="page-title">A6 — Fill Final Allocation DOCX Template</h2>
                <div class="text-secondary">Upload your own Word template, or download a Circular-ordered sample template with ready-to-use tags.</div>
            </div>
            <div class="col-auto ms-auto">
                <a class="btn btn-outline-secondary" href="{{ route('allocation.a6.index') }}">Back to A6 — Reporting &amp; Export</a>
            </div>
        </div>
    </div>
</div>

<div class="page-body">
    <div class="container-xl">
        <div class="row row-cards">
            <div class="col-lg-7">
                <div class="card h-100">
                    <div class="card-header">
                        <div>
                            <h3 class="card-title">Generate Final DOCX</h3>
                            <div class="card-subtitle">The uploaded template remains unchanged; a new timestamped document is generated through the export queue.</div>
                        </div>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('allocation.a6.docx.generate') }}" enctype="multipart/form-data">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label">Word template (.docx)</label>
                                <input class="form-control" type="file" name="template_file" accept=".docx" required>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-5">
                                    <label class="form-label">Result Date</label>
                                    <input class="form-control" type="date" name="result_date" value="{{ old('result_date', now()->format('Y-m-d')) }}" required>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label">Registrations per Line</label>
                                    <input class="form-control" type="number" name="registrations_per_line" min="1" max="20" value="{{ old('registrations_per_line', $defaultPerLine) }}" required>
                                    <div class="form-hint">Default: 8 registrations per line.</div>
                                </div>
                            </div>

                            <button class="btn btn-primary mt-4">Queue DOCX Generation</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card h-100">
                    <div class="card-header">
                        <div>
                            <h3 class="card-title">Sample DOCX Template</h3>
                            <div class="card-subtitle">Generated from the current finalized A5-bound Circular order.</div>
                        </div>
                    </div>
                    <div class="card-body d-flex flex-column">
                        <p class="text-secondary mb-3">
                            The sample groups General and Technical / Professional entries separately, follows Circular serial/sub-serial order, uses Bengali cadre/post names only, and includes the exact allocation tags.
                        </p>
                        <div class="alert alert-info">
                            You can edit the downloaded Word file freely. Keep the placeholder tags unchanged wherever allocation data should be inserted.
                        </div>
                        <a class="btn btn-outline-primary mt-auto align-self-start" href="{{ route('allocation.a6.docx.sample') }}">
                            Download Sample DOCX Template
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header">
                <h3 class="card-title">Supported placeholders</h3>
            </div>
            <div class="table-responsive">
                <table class="table table-vcenter card-table mb-0">
                    <thead>
                        <tr>
                            <th class="text-start">Purpose</th>
                            <th class="text-start">Placeholder</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td class="text-start align-middle">Cadre registrations</td><td class="text-start align-middle"><strong>[[110_ADMN]]</strong></td></tr>
                        <tr><td class="text-start align-middle">Cadre total</td><td class="text-start align-middle"><strong>[[TOTAL_110_ADMN]]</strong></td></tr>
                        <tr><td class="text-start align-middle">All allocated registrations</td><td class="text-start align-middle"><strong>[[ALL_ALLOCATED]]</strong></td></tr>
                        <tr><td class="text-start align-middle">Overall allocated total</td><td class="text-start align-middle"><strong>[[TOTAL_ALLOCATED]]</strong></td></tr>
                        <tr><td class="text-start align-middle">Exam name</td><td class="text-start align-middle"><strong>[[EXAM_NAME]]</strong></td></tr>
                        <tr><td class="text-start align-middle">Result date</td><td class="text-start align-middle"><strong>[[RESULT_DATE]]</strong></td></tr>
                        <tr><td class="text-start align-middle">A5 finalized date</td><td class="text-start align-middle"><strong>[[A5_FINALIZED_DATE]]</strong></td></tr>
                        <tr><td class="text-start align-middle">Report generation timestamp</td><td class="text-start align-middle"><strong>[REPORT_GENERATION_TIMESTAMP]</strong></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
