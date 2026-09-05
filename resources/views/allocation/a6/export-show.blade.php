@extends('layouts.app')

@section('content')
<div class="page-header d-print-none">
    <div class="container-xl">
        <div class="row g-2 align-items-center">
            <div class="col">
                <h2 class="page-title">A6 — Export Job #{{ $run->id }}</h2>
                <div class="text-secondary">
                    {{ $run->export_type }} · {{ $run->scope ?: '—' }}
                </div>
            </div>
            <div class="col-auto ms-auto">
                <a class="btn btn-outline-secondary" href="{{ route('allocation.a6.index') }}">
                    Back to A6 — Reporting &amp; Export
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

        @php
            $statusBadge = match ($run->status) {
                'completed' => 'success',
                'failed' => 'danger',
                default => 'azure',
            };

            $isRunning = in_array($run->status, ['queued', 'running'], true);
        @endphp

        <div
            class="card"
            id="a6-export-progress"
            data-status-url="{{ route('allocation.a6.exports.status', $run) }}"
            data-download-label="Download {{ $run->export_type }}"
        >
            <div class="card-header">
                <h3 class="card-title">Export Progress</h3>
                <div class="ms-auto">
                    <span id="a6-export-status" class="badge bg-{{ $statusBadge }}-lt">
                        {{ strtoupper($run->status) }}
                    </span>
                </div>
            </div>

            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <div class="fw-bold" id="a6-export-phase">
                            {{ strtoupper(str_replace('_', ' ', $run->phase ?: $run->status)) }}
                        </div>
                        <div class="small text-secondary" id="a6-export-message">
                            {{ $run->progress_message }}
                        </div>
                    </div>

                    <div class="fw-bold">
                        <span id="a6-export-percent">{{ (int) $run->progress_percent }}</span>%
                    </div>
                </div>

                <div class="progress progress-lg mb-2">
                    <div
                        id="a6-export-bar"
                        class="progress-bar{{ $isRunning ? ' progress-bar-striped progress-bar-animated' : '' }}"
                        style="width: {{ (int) $run->progress_percent }}%"
                    ></div>
                </div>

                <div class="small text-secondary" id="a6-export-count">
                    @if ((int) $run->progress_total > 0)
                        {{ number_format($run->progress_current) }}
                        of
                        {{ number_format($run->progress_total) }}
                        processed
                    @endif
                </div>

                <div
                    id="a6-export-error"
                    class="alert alert-danger mt-3{{ $run->status === 'failed' ? '' : ' d-none' }}"
                >
                    {{ $run->failure_message }}
                </div>

                <div class="mt-3" id="a6-export-download-wrap">
                    @if ($run->status === 'completed')
                        <a
                            id="a6-export-download"
                            class="btn btn-success"
                            href="{{ route('allocation.a6.exports.download', $run) }}"
                        >
                            Download {{ $run->export_type }}
                        </a>
                    @endif
                </div>
            </div>
        </div>

        @if (!empty($run->parameters))
            <div class="card mt-3">
                <div class="card-header">
                    <h3 class="card-title">Export Configuration</h3>
                </div>

                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <div class="text-secondary">Type</div>
                            <div class="fw-bold">{{ $run->export_type }}</div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-secondary">Scope</div>
                            <div class="fw-bold">{{ $run->scope ?: '—' }}</div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-secondary">Queued</div>
                            <div class="fw-bold">
                                {{ $run->queued_at ? $run->queued_at->format('d-m-Y h:i A') : '—' }}
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-secondary">Generated By</div>
                            <div class="fw-bold">
                                @if ($run->generated_by)
                                    {{ $run->generated_by }} - {{ $generatedByUser?->name ?? 'Unknown User' }}
                                @else
                                    System
                                @endif
                            </div>
                        </div>
                    </div>

                    @if (!empty($run->parameters['selected_field_labels']))
                        <hr>
                        <div class="text-secondary mb-2">Selected Excel Fields</div>
                        <div class="d-flex flex-wrap gap-1">
                            @foreach ($run->parameters['selected_field_labels'] as $label)
                                <span class="badge bg-secondary-lt">{{ $label }}</span>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>

@if ($isRunning)
    <script>
        (() => {
            const box = document.getElementById('a6-export-progress');

            if (!box) {
                return;
            }

            const formatNumber = (value) => Number(value || 0).toLocaleString();

            const poll = async () => {
                try {
                    const response = await fetch(box.dataset.statusUrl, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    if (!response.ok) {
                        throw new Error('Unable to read export status.');
                    }

                    const data = await response.json();
                    const percent = Math.max(
                        0,
                        Math.min(100, Number(data.progress_percent || 0))
                    );

                    document.getElementById('a6-export-percent').textContent = percent;
                    document.getElementById('a6-export-bar').style.width = percent + '%';
                    document.getElementById('a6-export-phase').textContent =
                        String(data.phase || data.status || '')
                            .replaceAll('_', ' ')
                            .toUpperCase();
                    document.getElementById('a6-export-message').textContent =
                        data.progress_message || '';
                    document.getElementById('a6-export-status').textContent =
                        String(data.status || '').toUpperCase();

                    document.getElementById('a6-export-count').textContent =
                        Number(data.progress_total || 0) > 0
                            ? formatNumber(data.progress_current)
                                + ' of '
                                + formatNumber(data.progress_total)
                                + ' processed'
                            : '';

                    if (data.failure_message) {
                        const errorBox = document.getElementById('a6-export-error');
                        errorBox.textContent = data.failure_message;
                        errorBox.classList.remove('d-none');
                    }

                    if (data.download_url) {
                        const wrapper = document.getElementById('a6-export-download-wrap');
                        const link = document.createElement('a');

                        link.className = 'btn btn-success';
                        link.href = data.download_url;
                        link.textContent = box.dataset.downloadLabel;

                        wrapper.replaceChildren(link);
                    }

                    if (!data.finished) {
                        window.setTimeout(poll, 1500);
                        return;
                    }

                    document
                        .getElementById('a6-export-bar')
                        .classList.remove('progress-bar-striped', 'progress-bar-animated');
                } catch (error) {
                    window.setTimeout(poll, 3500);
                }
            };

            window.setTimeout(poll, 800);
        })();
    </script>
@endif
@endsection
