@extends('layouts.app')

@section('title', 'Preliminary Mark Distribution')

@section('content')
<div class="page-header d-print-none">
    <div class="container-xl">
        <div class="row g-2 align-items-center">
            <div class="col">
                <h2 class="page-title">Preliminary Mark Distribution</h2>
                <div class="text-secondary">Mark-wise count, cumulative count and audited cut-off workflow</div>
            </div>
            <div class="col-auto ms-auto d-flex gap-2">
                <a class="btn btn-outline-primary" href="{{ route('preliminary.distribution.csv', $report) }}">Download CSV</a>
                <a class="btn btn-outline-secondary" href="{{ route('preliminary.index') }}">Back</a>
            </div>
        </div>
    </div>
</div>

<div class="page-body">
    <div class="container-xl">
        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ((int) $state->latest_distribution_report_id !== (int) $report->id)
            <div class="alert alert-warning">
                This is an older distribution snapshot. Cut-off proposals can only be approved against the latest distribution.
            </div>
        @endif

        <div class="row row-cards mb-3">
            <div class="col-sm-6 col-lg-3"><div class="card card-sm"><div class="card-body"><div class="text-secondary">Eligible</div><div class="h2 mb-0">{{ number_format((int) $report->eligible_candidates) }}</div></div></div></div>
            <div class="col-sm-6 col-lg-3"><div class="card card-sm"><div class="card-body"><div class="text-secondary">GG / TT / GT</div><div class="h3 mb-0">{{ number_format((int) $report->gg_candidates) }} / {{ number_format((int) $report->tt_candidates) }} / {{ number_format((int) $report->gt_candidates) }}</div></div></div></div>
            <div class="col-sm-6 col-lg-3"><div class="card card-sm"><div class="card-body"><div class="text-secondary">Mark Range</div><div class="h3 mb-0">{{ $report->minimum_mark ?? '—' }} – {{ $report->maximum_mark ?? '—' }}</div></div></div></div>
            <div class="col-sm-6 col-lg-3"><div class="card card-sm"><div class="card-body"><div class="text-secondary">Distinct Marks</div><div class="h2 mb-0">{{ number_format((int) $report->distinct_marks) }}</div></div></div></div>
        </div>

        @if ($currentCutoff)
            <div class="alert {{ $state->cutoff_requires_review ? 'alert-warning' : 'alert-success' }}">
                <strong>Approved cut-off:</strong> {{ number_format((float) $currentCutoff->cutoff_mark, 2) }}.
                Projected pass: {{ number_format((int) $currentCutoff->pass_total) }}
                (GG {{ number_format((int) $currentCutoff->pass_gg) }}, TT {{ number_format((int) $currentCutoff->pass_tt) }}, GT {{ number_format((int) $currentCutoff->pass_gt) }}).
                @if ($state->cutoff_requires_review)
                    This cut-off was preserved after downstream data changed and must be reviewed/re-approved before finalization.
                @endif
            </div>
        @endif

        @if ($pendingCutoff)
            <div class="card mb-3 border-primary">
                <div class="card-header"><h3 class="card-title">Pending Cut-off Proposal</h3></div>
                <div class="card-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-3"><div class="text-secondary">Cut-off</div><div class="h2">{{ number_format((float) $pendingCutoff->cutoff_mark, 2) }}</div></div>
                        <div class="col-md-3"><div class="text-secondary">Projected Pass</div><div class="h2">{{ number_format((int) $pendingCutoff->pass_total) }}</div></div>
                        <div class="col-md-6"><div class="text-secondary">Category</div><div class="h3">GG {{ number_format((int) $pendingCutoff->pass_gg) }} · TT {{ number_format((int) $pendingCutoff->pass_tt) }} · GT {{ number_format((int) $pendingCutoff->pass_gt) }}</div></div>
                    </div>
                    <div class="mb-3"><strong>Proposal reason:</strong> {{ $pendingCutoff->reason }}</div>

                    @can('process', App\Models\PreliminaryResult::class)
                        @if ((int) $pendingCutoff->distribution_report_id === (int) $state->latest_distribution_report_id)
                            <form method="POST" action="{{ route('preliminary.cutoff.approve', $pendingCutoff) }}">
                                @csrf
                                <div class="row g-2 align-items-end">
                                    <div class="col-md">
                                        <label class="form-label">Approval reason <span class="text-danger">*</span></label>
                                        <textarea class="form-control" name="approval_reason" rows="2" required>{{ old('approval_reason') }}</textarea>
                                    </div>
                                    <div class="col-md-auto">
                                        <button class="btn btn-success" type="submit">Approve Cut-off</button>
                                    </div>
                                </div>
                            </form>
                        @endif
                    @endcan
                </div>
            </div>
        @endif

        @can('process', App\Models\PreliminaryResult::class)
            @if ((int) $state->latest_distribution_report_id === (int) $report->id)
                <div class="card mb-3">
                    <div class="card-header"><h3 class="card-title">Propose / Revise Cut-off</h3></div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('preliminary.cutoff.propose') }}">
                            @csrf
                            <div class="row g-2 align-items-end">
                                <div class="col-md-3">
                                    <label class="form-label">Cut-off mark</label>
                                    <input class="form-control" type="number" name="cutoff_mark" step="0.01" value="{{ old('cutoff_mark', $state->cutoff_mark) }}" required>
                                </div>
                                <div class="col-md">
                                    <label class="form-label">Reason <span class="text-danger">*</span></label>
                                    <textarea class="form-control" name="reason" rows="2" required>{{ old('reason') }}</textarea>
                                </div>
                                <div class="col-md-auto">
                                    <button class="btn btn-primary" type="submit">Save Proposal</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            @endif
        @endcan

        <div class="card mb-3">
            <div class="card-header"><h3 class="card-title">Mark Distribution & Cumulative Count</h3></div>
            <div class="table-responsive" style="max-height: 650px;">
                <table class="table table-vcenter table-striped card-table">
                    <thead class="sticky-top bg-body">
                        <tr>
                            <th>Mark</th>
                            <th class="text-end">At Mark</th>
                            <th class="text-end">GG</th>
                            <th class="text-end">TT</th>
                            <th class="text-end">GT</th>
                            <th class="text-end">Cumulative</th>
                            <th class="text-end">Cum. GG</th>
                            <th class="text-end">Cum. TT</th>
                            <th class="text-end">Cum. GT</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr @if ((string) $state->cutoff_mark === (string) $row['mark']) class="table-success" @endif>
                                <td><strong>{{ $row['mark'] }}</strong></td>
                                <td class="text-end">{{ number_format((int) $row['count']['total']) }}</td>
                                <td class="text-end">{{ number_format((int) $row['count']['GG']) }}</td>
                                <td class="text-end">{{ number_format((int) $row['count']['TT']) }}</td>
                                <td class="text-end">{{ number_format((int) $row['count']['GT']) }}</td>
                                <td class="text-end"><strong>{{ number_format((int) $row['cumulative']['total']) }}</strong></td>
                                <td class="text-end">{{ number_format((int) $row['cumulative']['GG']) }}</td>
                                <td class="text-end">{{ number_format((int) $row['cumulative']['TT']) }}</td>
                                <td class="text-end">{{ number_format((int) $row['cumulative']['GT']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="text-center text-secondary py-4">No eligible mark rows.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h3 class="card-title">Cut-off Decision History</h3></div>
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead><tr><th>ID</th><th>Mark</th><th>Status</th><th>Projected Pass</th><th>Proposal Reason</th><th>Approval Reason</th><th>Times</th></tr></thead>
                    <tbody>
                        @forelse ($cutoffHistory as $decision)
                            <tr>
                                <td>#{{ $decision->id }}</td>
                                <td>{{ number_format((float) $decision->cutoff_mark, 2) }}</td>
                                <td>{{ ucfirst($decision->status) }}</td>
                                <td>{{ number_format((int) $decision->pass_total) }}</td>
                                <td>{{ $decision->reason }}</td>
                                <td>{{ $decision->approval_reason ?? '—' }}</td>
                                <td>{{ $decision->proposed_at?->format('d-m-Y h:i A') }} @if ($decision->approved_at) → {{ $decision->approved_at->format('d-m-Y h:i A') }} @endif</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-secondary">No cut-off decisions yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
