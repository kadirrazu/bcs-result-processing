@extends('layouts.app')

@section('title', 'Written Processing')

@section('content')
<div class="page-header d-print-none">
    <div class="container-xl">
        <div class="row g-2 align-items-center">
            <div class="col">
                <h2 class="page-title">Written Processing</h2>
                <div class="text-secondary">
                    Import → Validation → Review → Approval/Merge → Reconciliation → Paper Crash → Finalization
                </div>
            </div>
            <div class="col-auto ms-auto">
                <a href="{{ route('written.template') }}" class="btn btn-outline-primary">Download Import Template</a>
            </div>
        </div>
    </div>
</div>

<div class="page-body">
    <div class="container-xl">
        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="row row-cards mb-3">
            <div class="col-sm-6 col-lg-3">
                <div class="card card-sm"><div class="card-body"><div class="text-secondary">Written Results</div><div class="h2 mb-0">{{ number_format($counts['results']) }}</div></div></div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card card-sm"><div class="card-body"><div class="text-secondary">Warning Review</div><div class="h2 mb-0 text-warning">{{ number_format($counts['warnings']) }}</div></div></div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card card-sm"><div class="card-body"><div class="text-secondary">Cancelled</div><div class="h2 mb-0">{{ number_format($counts['cancelled']) }}</div></div></div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card card-sm"><div class="card-body"><div class="text-secondary">Withheld</div><div class="h2 mb-0">{{ number_format($counts['withheld']) }}</div></div></div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title">Written Rule Configuration</h3>
                <div class="card-actions text-secondary small">Source: config/written.php</div>
            </div>
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead><tr><th>Rule</th><th>Configured Value</th></tr></thead>
                    <tbody>
                        <tr><td>General track full mark</td><td>{{ number_format($ruleSummary['general_full_mark'], 2) }}</td></tr>
                        <tr><td>Technical track full mark</td><td>{{ number_format($ruleSummary['technical_full_mark'], 2) }}</td></tr>
                        <tr><td>Written pass threshold</td><td>{{ number_format((float) config('written.written_pass_percent'), 2) }}% — General {{ number_format($ruleSummary['general_pass_mark'], 2) }}, Technical {{ number_format($ruleSummary['technical_pass_mark'], 2) }}</td></tr>
                        <tr><td>Paper crash threshold</td><td>{{ number_format($ruleSummary['paper_crash_percent'], 2) }}%</td></tr>
                        <tr><td>High-mark review threshold</td><td>{{ number_format($ruleSummary['high_mark_review_percent'], 2) }}%</td></tr>
                        <tr><td>008 + 009</td><td>Combined evaluation for crash and high-mark review; full mark 100</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><h3 class="card-title">Processing Status Board</h3><div class="card-actions text-secondary small">GMT+6 (Asia/Dhaka)</div></div>
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead><tr><th>Step</th><th>Status</th><th>Summary</th></tr></thead>
                    <tbody>
                        <tr><td>Written Marks Import</td><td>{{ $latestBatch?->status ?? 'Not started' }}</td><td>{{ $latestBatch ? number_format((int) $latestBatch->approved_rows).' approved rows' : '—' }}</td></tr>
                        <tr><td>Eligible / Appeared / Absent</td><td>{{ $state->reconciliation_generated_at ? 'Generated' : 'Pending' }}</td><td>Completely absent includes no source row OR all applicable marks ABS/AAA.</td></tr>
                        <tr><td>Paper Crash Processing</td><td>Pending</td><td>Actual marks preserved; counted marks may become zero.</td></tr>
                        <tr><td>Written Result Finalization</td><td>{{ $state->result_finalized_at ? 'Completed' : 'Pending' }}</td><td>Derived written_qualified_track: GG / TT / GT / GN / T</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="alert alert-info">
            <strong>W1 Foundation installed.</strong> The next phase wires fast staging import, warning-first validation listing, PRS mismatch review, and high-mark review.
        </div>

        @if ($audits->isNotEmpty())
            <div class="card">
                <div class="card-header"><h3 class="card-title">Latest Written Audit Events</h3></div>
                <div class="table-responsive">
                    <table class="table table-sm card-table">
                        <thead><tr><th>Action</th><th>Actor</th><th>Reason</th><th>Time</th></tr></thead>
                        <tbody>
                            @foreach ($audits as $audit)
                                <tr>
                                    <td>{{ $audit->action }}</td>
                                    <td>{{ $audit->actor_name ?: $audit->actor_id }}</td>
                                    <td>{{ $audit->reason ?: '—' }}</td>
                                    <td>{{ $audit->created_at?->format('d-m-Y h:i A') ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
