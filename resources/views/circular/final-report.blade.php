@extends('layouts.app')

@section('title', 'Final Circular Report')

@section('content')
<div class="page-header d-print-none">
    <div class="container-xl">
        <div class="row align-items-center">
            <div class="col">
                <h2 class="page-title">Final Circular Report</h2>
                <div class="text-secondary">Finalized Circular verification summary and downstream hand-off checkpoint.</div>
            </div>
            <div class="col-auto ms-auto d-print-none">
                <div class="d-flex gap-2 flex-wrap">
                    <a class="btn btn-outline-secondary" href="{{ route('circular.index') }}">Back to Circular</a>
                    @if($summary['ready'])
                        <a class="btn btn-outline-primary" href="{{ route('circular.final-report.excel') }}">Export Excel</a>
                        <a class="btn btn-primary" href="{{ route('circular.final-report.pdf') }}">Export PDF</a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<div class="page-body">
    <div class="container-xl">
        @if(!$summary['ready'])
            <div class="alert alert-warning">
                Final report exports are locked until the current Circular is Approved, Authority Confirmed and Finalized on the same version.
            </div>
        @else
            <div class="alert alert-success">
                <strong>Downstream-ready:</strong> Circular version {{ $summary['version'] }} is finalized and is the only version that should be consumed by Choice Validation and later eligibility processing.
            </div>
        @endif

        <div class="row row-cards mb-3">
            @foreach([
                'Circular Version' => $summary['version'],
                'Total Entries' => $summary['entry_count'],
                'General Posts' => $summary['general_posts'],
                'Technical Posts' => $summary['technical_posts'],
                'Total Approved Posts' => $summary['total_posts'],
            ] as $label => $value)
                <div class="col-sm-6 col-lg">
                    <div class="card card-sm h-100">
                        <div class="card-body">
                            <div class="text-secondary">{{ $label }}</div>
                            <div class="h2 mb-0">{{ is_numeric($value) ? number_format($value) : $value }}</div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="card mb-3">
            <div class="card-header"><h3 class="card-title">Finalization Verification</h3></div>
            <div class="table-responsive">
                <table class="table table-vcenter card-table mb-0">
                    <tbody>
                        <tr><td class="fw-medium">Current Version</td><td>v{{ $state->current_version }}</td></tr>
                        <tr><td class="fw-medium">Approved Version</td><td>{{ $state->approved_version ? 'v'.$state->approved_version : '—' }}</td></tr>
                        <tr><td class="fw-medium">Confirmed Version</td><td>{{ $state->confirmed_version ? 'v'.$state->confirmed_version : '—' }}</td></tr>
                        <tr><td class="fw-medium">Finalized Version</td><td>{{ $state->finalized_version ? 'v'.$state->finalized_version : '—' }}</td></tr>
                        <tr><td class="fw-medium">Confirmed At</td><td>{{ $state->confirmed_at?->format('d M Y, h:i:s A') ?? '—' }}</td></tr>
                        <tr><td class="fw-medium">Finalized At</td><td>{{ $state->finalized_at?->format('d M Y, h:i:s A') ?? '—' }}</td></tr>
                        <tr><td class="fw-medium">Confirmation Notes</td><td>{{ $summary['confirmation_notes'] ?: '—' }}</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        @if($summary['ready'])
            <div class="card">
                <div class="card-header">
                    <div>
                        <h3 class="card-title mb-1">Finalized Circular Entries</h3>
                        <div class="text-secondary small">Read-only snapshot from finalized version {{ $summary['version'] }}.</div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-vcenter card-table mb-0">
                        <thead><tr><th>Serial</th><th>Cadre / Post</th><th>Code</th><th>Type</th><th>Posts</th><th>Eligibility</th></tr></thead>
                        <tbody>
                        @foreach($entries as $entry)
                            <tr>
                                <td>{{ $entry->cadre_serial }}@if($entry->sub_serial !== null).{{ $entry->sub_serial }}@endif</td>
                                <td>
                                    <div class="fw-medium">{{ $entry->cadre_name_snapshot }}</div>
                                    <div class="text-secondary">{{ $entry->post_name_snapshot ?: '—' }}</div>
                                </td>
                                <td><span class="badge bg-azure-lt text-azure">{{ $entry->effective_code }}</span></td>
                                <td>{{ $entry->cadre_type->value }}</td>
                                <td>{{ number_format($entry->post_count) }}</td>
                                <td class="small">
                                    @if($entry->cadre_type->value === 'TT')
                                        <div><strong>Bachelor:</strong> {{ $entry->bachelorSubjects->pluck('subject_code')->implode(', ') ?: '—' }}</div>
                                        <div><strong>PRS:</strong> {{ $entry->prsSubjects->pluck('prs_code')->implode(', ') ?: '—' }}</div>
                                    @else
                                        <span class="text-secondary">No subject/PRS restriction</span>
                                    @endif
                                </td>
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
