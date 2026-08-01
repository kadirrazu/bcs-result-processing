@extends('layouts.app')

@section('title', 'Preliminary Processing')

@section('content')
<div class="page-header d-print-none">
    <div class="container-xl">
        <div class="row g-2 align-items-center">
            <div class="col">
                <h2 class="page-title">Preliminary Processing</h2>
                <div class="text-secondary">
                    Fast staging → Validation → Approval/Merge → Reconciliation → Cut-off → Finalization
                </div>
            </div>

            <div class="col-auto ms-auto d-flex gap-2">
                <a href="{{ route('preliminary.results.index') }}" class="btn btn-outline-primary">
                    Preliminary Results
                </a>
                <a href="{{ route('preliminary.template') }}" class="btn btn-outline-primary">
                    Download Import Template
                </a>
            </div>
        </div>
    </div>
</div>

<div class="page-body">
    <div class="container-xl">
        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if ($latestFinalization && in_array($latestFinalization->status, ['queued', 'running'], true))
            <div
                id="preliminary-finalization-progress"
                class="card mb-3"
                data-status-url="{{ route('preliminary.finalization.status', $latestFinalization) }}"
            >
                <div class="card-body">
                    <div class="d-flex align-items-center mb-2">
                        <div>
                            <div class="fw-semibold">Final Preliminary Result Processing</div>
                            <div class="text-secondary" id="finalization-current-step">
                                {{ $latestFinalization->current_step ?: 'Waiting for queue worker' }}
                            </div>
                        </div>
                        <div class="ms-auto text-end">
                            <span id="finalization-live-status" class="badge bg-blue-lt">{{ ucfirst($latestFinalization->status) }}</span>
                            <div class="text-secondary small mt-1">Cut-off {{ number_format((float) $latestFinalization->cutoff_mark, 2) }}</div>
                        </div>
                    </div>

                    @php($initialFinalizationProgress = (float) ($latestFinalization->progress_percent ?? 0))
                    <div class="progress progress-sm mb-2">
                        <div
                            id="finalization-progress-bar"
                            class="progress-bar progress-bar-striped progress-bar-animated"
                            role="progressbar"
                            style="width: {{ $initialFinalizationProgress }}%"
                            aria-valuenow="{{ $initialFinalizationProgress }}"
                            aria-valuemin="0"
                            aria-valuemax="100"
                        ></div>
                    </div>

                    <div class="d-flex justify-content-between small text-secondary">
                        <span id="finalization-row-progress">
                            {{ number_format((int) ($latestFinalization->processed_rows ?? 0)) }} /
                            {{ number_format((int) ($latestFinalization->total_rows ?? 0)) }} rows
                        </span>
                        <strong id="finalization-progress-percent">{{ number_format($initialFinalizationProgress, 2) }}%</strong>
                    </div>

                    <div id="finalization-live-error" class="alert alert-danger mt-3 mb-0 d-none"></div>
                </div>
            </div>
        @endif

        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title">Processing Status Board</h3>
                <div class="card-actions text-secondary small">Time zone: GMT+6 (Asia/Dhaka)</div>
            </div>

            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                        <tr>
                            <th>Step</th>
                            <th>Status / Summary</th>
                            <th>Completed At</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Mark Imported</td>
                            <td>
                                {{ $latestBatch?->status ?? 'Not started' }}
                                @if ($latestBatch)
                                    — {{ number_format((int) $latestBatch->approved_rows) }} approved rows
                                @endif
                            </td>
                            <td>{{ $latestBatch?->approved_at?->format('d-m-Y h:i A') ?? '—' }}</td>
                            <td></td>
                        </tr>

                        <tr>
                            <td>Present / Absent Report</td>
                            <td>
                                {{ $state->reconciliation_generated_at ? 'Generated' : 'Pending / stale' }}
                            </td>
                            <td>{{ $state->reconciliation_generated_at?->format('d-m-Y h:i A') ?? '—' }}</td>
                            <td class="text-end">
                                @can('process', App\Models\PreliminaryResult::class)
                                    @if ($latestBatch && $latestBatch->status === 'approved')
                                        <form
                                            class="d-inline"
                                            method="POST"
                                            action="{{ route('preliminary.reconciliation.generate') }}"
                                        >
                                            @csrf
                                            <button class="btn btn-sm btn-primary" type="submit">
                                                {{ $state->reconciliation_generated_at ? 'Regenerate' : 'Generate' }}
                                            </button>
                                        </form>
                                    @endif
                                @endcan

                                @if ($latestReconciliation)
                                    <a
                                        class="btn btn-sm btn-outline-primary"
                                        href="{{ route('preliminary.reconciliation.show', $latestReconciliation) }}"
                                    >
                                        View Latest
                                    </a>
                                @endif
                            </td>
                        </tr>

                        <tr>
                            <td>Mark Distribution</td>
                            <td>
                                {{ $state->distribution_generated_at ? 'Generated' : 'Pending / stale' }}
                                @if ($latestDistribution)
                                    — {{ number_format((int) $latestDistribution->eligible_candidates) }} eligible candidates
                                @endif
                            </td>
                            <td>{{ $state->distribution_generated_at?->format('d-m-Y h:i A') ?? '—' }}</td>
                            <td class="text-end">
                                @can('process', App\Models\PreliminaryResult::class)
                                    @if ($state->latest_reconciliation_report_id)
                                        <form class="d-inline" method="POST" action="{{ route('preliminary.distribution.generate') }}">
                                            @csrf
                                            <button class="btn btn-sm btn-primary" type="submit">
                                                {{ $state->distribution_generated_at ? 'Regenerate' : 'Generate' }}
                                            </button>
                                        </form>
                                    @endif
                                @endcan
                                @if ($latestDistribution)
                                    <a class="btn btn-sm btn-outline-primary" href="{{ route('preliminary.distribution.show', $latestDistribution) }}">View Latest</a>
                                @endif
                            </td>
                        </tr>

                        <tr>
                            <td>Cut-off Mark</td>
                            <td>
                                @if ($state->cutoff_mark !== null)
                                    Set as {{ number_format((float) $state->cutoff_mark, 2) }}
                                    @if ($state->cutoff_requires_review)
                                        <span class="badge bg-yellow-lt ms-1">Review required</span>
                                    @endif
                                @elseif ($pendingCutoff)
                                    Proposed: {{ number_format((float) $pendingCutoff->cutoff_mark, 2) }} — awaiting approval
                                @else
                                    Pending
                                @endif
                            </td>
                            <td>{{ $state->cutoff_set_at?->format('d-m-Y h:i A') ?? '—' }}</td>
                            <td class="text-end">
                                @if ($latestDistribution)
                                    <a class="btn btn-sm btn-outline-primary" href="{{ route('preliminary.distribution.show', $latestDistribution) }}">Manage Cut-off</a>
                                @endif
                            </td>
                        </tr>

                        <tr>
                            <td>Result Finalized</td>
                            <td id="finalization-board-status">
                                @if ($latestFinalization && in_array($latestFinalization->status, ['queued', 'running'], true))
                                    <span class="badge bg-blue-lt">{{ ucfirst($latestFinalization->status) }}</span>
                                    — Cut-off {{ number_format((float) $latestFinalization->cutoff_mark, 2) }}
                                @elseif ($state->result_finalized_at)
                                    <span class="badge bg-green-lt">Completed</span>
                                    @if (data_get($state->summary, 'finalization.pass.total') !== null)
                                        — Passed: {{ number_format((int) data_get($state->summary, 'finalization.pass.total')) }}
                                    @endif
                                @elseif ($latestFinalization && $latestFinalization->status === 'failed')
                                    <span class="badge bg-red-lt">Failed</span>
                                @else
                                    Pending
                                @endif
                            </td>
                            <td id="finalization-board-completed-at">{{ $state->result_finalized_at?->format('d-m-Y h:i A') ?? '—' }}</td>
                            <td class="text-end">
                                @if ($state->result_finalized_at)
                                    <a class="btn btn-sm btn-outline-primary" href="{{ route('preliminary.final-result.combined') }}">Combined Result</a>
                                    <a class="btn btn-sm btn-outline-primary" href="{{ route('preliminary.final-result.category') }}">GG / TT / GT</a>
                                @endif
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        @can('process', App\Models\PreliminaryResult::class)
            @if ($state->cutoff_mark !== null && ! $state->cutoff_requires_review && ! ($latestFinalization && in_array($latestFinalization->status, ['queued', 'running'], true)))
                <div class="card mb-3">
                    <div class="card-header">
                        <h3 class="card-title">Final Preliminary Result Processing</h3>
                    </div>
                    <div class="card-body">
                        <p class="text-secondary">
                            Apply approved cut-off <strong>{{ number_format((float) $state->cutoff_mark, 2) }}</strong> to current eligible candidates.
                            Finalization is audited in both database and preliminary file log.
                        </p>
                        <form method="POST" action="{{ route('preliminary.finalization.store') }}">
                            @csrf
                            <div class="row g-2 align-items-end">
                                <div class="col-md">
                                    <label class="form-label">Reason / authority reference</label>
                                    <input class="form-control @error('reason') is-invalid @enderror" name="reason" value="{{ old('reason') }}" required minlength="5" maxlength="2000" placeholder="e.g. Finalize result against approved cut-off decision">
                                    @error('reason')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-auto">
                                    <button class="btn btn-success" type="submit" onclick="return confirm('Finalize preliminary result using the approved cut-off?');">
                                        {{ $state->result_finalized_at ? 'Re-Finalize Result' : 'Finalize Result' }}
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            @endif
        @endcan

        @if (is_array($state->summary) && data_get($state->summary, 'finalization'))
            <div class="card mb-3">
                <div class="card-header"><h3 class="card-title">Final Result Summary</h3></div>
                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead><tr><th>Result</th><th class="text-end">Total</th><th class="text-end">GG</th><th class="text-end">TT</th><th class="text-end">GT</th></tr></thead>
                        <tbody>
                            @foreach (['pass' => 'Passed', 'fail' => 'Failed', 'cancelled' => 'Cancelled', 'absent' => 'Absent'] as $key => $label)
                                <tr>
                                    <td>{{ $label }}</td>
                                    <td class="text-end">{{ number_format((int) data_get($state->summary, 'finalization.'.$key.'.total', 0)) }}</td>
                                    <td class="text-end">{{ number_format((int) data_get($state->summary, 'finalization.'.$key.'.GG', 0)) }}</td>
                                    <td class="text-end">{{ number_format((int) data_get($state->summary, 'finalization.'.$key.'.TT', 0)) }}</td>
                                    <td class="text-end">{{ number_format((int) data_get($state->summary, 'finalization.'.$key.'.GT', 0)) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <div class="row row-cards mb-3">
            @foreach ([
                'Results' => $counts['results'],
                'Active with mark' => $counts['active'],
                'Cancelled' => $counts['cancelled'],
                'Passed' => $counts['passed'],
                'Failed' => $counts['failed'],
            ] as $label => $value)
                <div class="col-sm-6 col-lg">
                    <div class="card card-sm">
                        <div class="card-body">
                            <div class="text-secondary">{{ $label }}</div>
                            <div class="h2 mb-0">{{ number_format($value) }}</div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        @can('process', App\Models\PreliminaryResult::class)
            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title">Preliminary Mark Import</h3>
                </div>

                <div class="card-body">
                    <p class="text-secondary">
                        Use exactly four columns:
                        <code>user</code>, <code>reg</code>, <code>mark</code>, <code>candidate_status</code>.
                    </p>

                    <form
                        method="POST"
                        enctype="multipart/form-data"
                        action="{{ route('preliminary.import.store') }}"
                    >
                        @csrf

                        <div class="row g-2 align-items-end">
                            <div class="col-md">
                                <label class="form-label">XLSX / CSV file</label>
                                <input
                                    class="form-control @error('file') is-invalid @enderror"
                                    type="file"
                                    name="file"
                                    accept=".xlsx,.csv"
                                    required
                                >
                                @error('file')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-auto">
                                <button class="btn btn-primary" type="submit">Queue Fast Import</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        @endcan

        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title">Import History</h3>
            </div>

            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                        <tr>
                            <th>Batch</th>
                            <th>File</th>
                            <th>Status</th>
                            <th>Rows</th>
                            <th>Valid</th>
                            <th>Warnings</th>
                            <th>Invalid</th>
                            <th>Approved</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($batches as $batch)
                            <tr>
                                <td>#{{ $batch->id }}</td>
                                <td>{{ $batch->original_name }}</td>
                                <td>{{ str_replace('_', ' ', $batch->status) }}</td>
                                <td>{{ number_format($batch->total_rows) }}</td>
                                <td>{{ number_format($batch->valid_rows) }}</td>
                                <td>{{ number_format($batch->warning_rows) }}</td>
                                <td>{{ number_format($batch->invalid_rows) }}</td>
                                <td>{{ number_format($batch->approved_rows) }}</td>
                                <td>
                                    <a
                                        class="btn btn-sm btn-outline-primary"
                                        href="{{ route('preliminary.import.result', $batch) }}"
                                    >
                                        View
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center text-secondary py-4">
                                    No preliminary import batches yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($batches->hasPages())
                <div class="card-footer">{{ $batches->links() }}</div>
            @endif
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Recent Preliminary Audit</h3>
            </div>

            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                        <tr>
                            <th>Action</th>
                            <th>Actor</th>
                            <th>Reason</th>
                            <th>Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($audits as $audit)
                            <tr>
                                <td>{{ $audit->action }}</td>
                                <td>{{ $audit->actor_name ?? $audit->actor_id }}</td>
                                <td>{{ $audit->reason ?? '—' }}</td>
                                <td>{{ $audit->created_at?->format('d-m-Y h:i A') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-secondary">
                                    No audit entries yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
@if ($latestFinalization && in_array($latestFinalization->status, ['queued', 'running'], true))
<script>
document.addEventListener('DOMContentLoaded', () => {
    const card = document.getElementById('preliminary-finalization-progress');
    if (!card) {
        return;
    }

    const statusUrl = card.dataset.statusUrl;
    const bar = document.getElementById('finalization-progress-bar');
    const percentText = document.getElementById('finalization-progress-percent');
    const rowText = document.getElementById('finalization-row-progress');
    const stepText = document.getElementById('finalization-current-step');
    const liveStatus = document.getElementById('finalization-live-status');
    const boardStatus = document.getElementById('finalization-board-status');
    const completedAt = document.getElementById('finalization-board-completed-at');
    const errorBox = document.getElementById('finalization-live-error');

    const number = new Intl.NumberFormat('en-US');
    let stopped = false;

    const setStatusBadge = (status) => {
        liveStatus.textContent = status.charAt(0).toUpperCase() + status.slice(1);
        liveStatus.className = 'badge ' + (status === 'failed' ? 'bg-red-lt' : status === 'completed' ? 'bg-green-lt' : 'bg-blue-lt');
    };

    const poll = async () => {
        if (stopped) {
            return;
        }

        try {
            const response = await fetch(statusUrl, {
                headers: { 'Accept': 'application/json' },
                cache: 'no-store',
            });

            if (!response.ok) {
                throw new Error(`Status request failed (${response.status})`);
            }

            const data = await response.json();
            const progress = Math.max(0, Math.min(100, Number(data.progress_percent || 0)));
            const total = Number(data.total_rows || 0);
            const processed = Number(data.processed_rows || 0);

            bar.style.width = `${progress}%`;
            bar.setAttribute('aria-valuenow', progress.toFixed(2));
            percentText.textContent = `${progress.toFixed(2)}%`;
            rowText.textContent = `${number.format(processed)} / ${number.format(total)} rows`;
            stepText.textContent = data.current_step || data.status;
            setStatusBadge(data.status);

            if (boardStatus) {
                const cutoff = Number(data.cutoff_mark || 0).toFixed(2);
                boardStatus.innerHTML = `<span class="badge ${data.status === 'failed' ? 'bg-red-lt' : data.status === 'completed' ? 'bg-green-lt' : 'bg-blue-lt'}">${data.status.charAt(0).toUpperCase() + data.status.slice(1)}</span> — Cut-off ${cutoff}`;
            }

            if (data.completed_at && completedAt) {
                completedAt.textContent = data.completed_at;
            }

            if (data.status === 'failed') {
                stopped = true;
                bar.classList.remove('progress-bar-animated');
                bar.classList.add('bg-danger');
                errorBox.textContent = data.failure_message || 'Preliminary finalization failed.';
                errorBox.classList.remove('d-none');
                window.setTimeout(() => window.location.reload(), 1500);
                return;
            }

            if (data.status === 'completed') {
                stopped = true;
                bar.style.width = '100%';
                bar.setAttribute('aria-valuenow', '100');
                percentText.textContent = '100.00%';
                stepText.textContent = 'Completed';
                bar.classList.remove('progress-bar-animated');
                window.setTimeout(() => window.location.reload(), 800);
                return;
            }
        } catch (error) {
            // A temporary polling failure must not interrupt the backend queue job.
            console.warn('Preliminary finalization progress polling failed:', error);
        }

        window.setTimeout(poll, 1500);
    };

    poll();
});
</script>
@endif
@endpush

