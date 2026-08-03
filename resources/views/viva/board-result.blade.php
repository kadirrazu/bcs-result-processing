@extends('layouts.app')

@section('title', 'Viva Board Import')

@section('content')
@php
    $activeProcessingStatuses = [
        'queued',
        'staging',
        'validation_queued',
        'validating',
        'approval_queued',
        'approving',
    ];
    $showProgress = in_array($record->status, $activeProcessingStatuses, true);
    $canValidate = in_array($record->status, ['staged', 'validated', 'failed'], true)
        && (int) $record->staged_rows > 0;
    $canApprove = $record->status === 'validated'
        && ((int) $record->valid_rows + (int) $record->warning_rows) > 0;
    $canCorrect = (int) $record->invalid_rows > 0;
    $canRetryStaging = $record->status === 'failed' && (int) $record->approved_rows === 0;
@endphp

<div class="page-wrapper">
    <div class="page-body">
        <div class="container-xl">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h2 class="page-title">Viva Board Data · Batch #{{ $record->id }}</h2>
                    <div class="text-secondary">{{ $record->original_name }}</div>
                </div>
                <a href="{{ route('viva.index') }}" class="btn btn-outline-secondary">Back to Viva</a>
            </div>

            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            @if ($record->failure_message)
                <div class="alert alert-danger">{{ $record->failure_message }}</div>
            @endif

            <div id="viva-board-progress" class="card mb-3" @unless($showProgress) style="display:none" @endunless>
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <strong>Processing Viva Board data</strong>
                        <span id="vb-status">{{ \App\Support\VivaStatusPresenter::label($record->status) }}</span>
                    </div>
                    <div class="progress mt-2">
                        <div id="vb-bar" class="progress-bar" style="width: {{ (float) $record->progress_percent }}%"></div>
                    </div>
                    <div class="text-secondary small mt-2" id="vb-count">
                        {{ number_format($record->processed_rows) }} processed
                    </div>
                </div>
            </div>

            <div class="row row-cards mb-3">
                @foreach ([
                    'Total' => $record->total_rows,
                    'Valid' => $record->valid_rows,
                    'Warnings' => $record->warning_rows,
                    'Invalid' => $record->invalid_rows,
                    'Approved' => $record->approved_rows,
                ] as $label => $value)
                    <div class="col">
                        <div class="card card-sm h-100">
                            <div class="card-body">
                                <div class="text-secondary">{{ $label }}</div>
                                <div class="h2">{{ number_format($value) }}</div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="card mb-3">
                <div class="card-body d-flex flex-wrap gap-2">
                    @if ($canValidate)
                        <form method="post" action="{{ route('viva.board.validate', $record) }}">
                            @csrf
                            <button class="btn btn-primary">
                                {{ $record->status === 'validated' ? 'Retry Validation' : 'Validate Board Data' }}
                            </button>
                        </form>
                    @endif

                    @if ($canApprove)
                        <form method="post" action="{{ route('viva.board.approve', $record) }}">
                            @csrf
                            <button class="btn btn-success">
                                Approve &amp; Merge {{ number_format((int) $record->valid_rows + (int) $record->warning_rows) }} Rows
                            </button>
                        </form>
                    @endif

                    @if ($canCorrect)
                        <a class="btn btn-outline-warning" href="{{ route('viva.board.corrections.template', $record) }}">
                            Download Invalid Rows
                        </a>
                        <form method="post" action="{{ route('viva.board.corrections.store', $record) }}" enctype="multipart/form-data" class="d-flex gap-2">
                            @csrf
                            <input class="form-control" type="file" name="correction_file" accept=".xlsx,.csv" required>
                            <button class="btn btn-warning">Upload Corrections</button>
                        </form>
                    @endif

                    @if ($canRetryStaging)
                        <form method="post" action="{{ route('viva.board.retry', $record) }}">
                            @csrf
                            <button class="btn btn-outline-danger">Retry Staging</button>
                        </form>
                    @endif
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <form class="row g-2 w-100">
                        <div class="col-md-3">
                            <select name="validation" class="form-select">
                                <option value="all">All validation states</option>
                                @foreach (['invalid', 'warning', 'valid', 'pending'] as $statusOption)
                                    <option value="{{ $statusOption }}" @selected($validation === $statusOption)>
                                        {{ ucfirst($statusOption) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <input name="search" value="{{ $search }}" class="form-control" placeholder="Code or member ID">
                        </div>
                        <div class="col-auto">
                            <button class="btn btn-outline-primary">Filter</button>
                        </div>
                    </form>
                </div>

                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead>
                            <tr>
                                <th>Row</th><th>Code</th><th>Date</th><th>Member</th><th>Mark</th>
                                <th>Attendance</th><th>Viva quota flags</th><th>Source flags</th>
                                <th>Validation</th><th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($rows as $row)
                                @php
                                    $rowClass = '';
                                    if ($row->validation_status === 'invalid') {
                                        $rowClass = 'table-danger';
                                    } elseif ($row->validation_status === 'warning') {
                                        $rowClass = 'table-warning';
                                    }
                                @endphp
                                <tr class="{{ $rowClass }}">
                                    <td>{{ $row->source_row }}</td>
                                    <td><code>{{ $row->code }}</code></td>
                                    <td>{{ $row->raw_viva_date ?? '—' }}</td>
                                    <td>{{ $row->member_id ?? '—' }}</td>
                                    <td>{{ $row->raw_mark ?? '—' }}</td>
                                    <td>{{ $row->attendance_status ?? '—' }}</td>
                                    <td>
                                        @if ($row->viva_cff) CFF @endif
                                        @if ($row->viva_em) EM @endif
                                        @if ($row->viva_phc) PHC @endif
                                    </td>
                                    <td>
                                        @if ($row->invalid_flag) INVALID @endif
                                        @if ($row->issue_flag) ISSUE @endif
                                    </td>
                                    <td>
                                        <span class="badge {{ \App\Support\VivaStatusPresenter::badgeClass($row->validation_status) }}">
                                            {{ ucfirst($row->validation_status) }}
                                        </span>
                                    </td>
                                    <td class="small">
                                        @foreach ((array) $row->validation_errors as $error)
                                            <div class="text-danger">{{ $error }}</div>
                                        @endforeach
                                        @foreach ((array) $row->validation_warnings as $warning)
                                            <div class="text-warning">{{ $warning }}</div>
                                        @endforeach
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="text-center text-secondary py-4">No rows found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="card-footer d-flex justify-content-between align-items-center">
                    <span class="text-secondary">
                        @if ($rows->total())
                            Displaying {{ number_format($rows->firstItem()) }} to {{ number_format($rows->lastItem()) }} of {{ number_format($rows->total()) }} records
                        @endif
                    </span>
                    {{ $rows->links() }}
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const box = document.getElementById('viva-board-progress');
    if (!box || box.style.display === 'none') return;

    const poll = () => {
        fetch(@json(route('viva.board.status', $record)), {
            headers: {'Accept': 'application/json'}
        })
        .then(response => response.json())
        .then(data => {
            document.getElementById('vb-status').textContent = data.status.replaceAll('_', ' ');
            document.getElementById('vb-bar').style.width = data.progress_percent + '%';
            document.getElementById('vb-count').textContent = Number(data.processed_rows).toLocaleString() + ' processed';

            if (data.finished) {
                box.style.display = 'none';
                location.reload();
                return;
            }

            setTimeout(poll, 1500);
        })
        .catch(() => setTimeout(poll, 3000));
    };

    setTimeout(poll, 1000);
});
</script>
@endsection
