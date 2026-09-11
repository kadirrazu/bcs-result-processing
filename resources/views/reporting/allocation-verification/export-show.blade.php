@extends('layouts.app')

@section('title', ($booklet ?? false) ? 'Booklet Printing PDF Export' : 'Verification PDF Export')

@section('page-header')
<div class="row g-2 align-items-center">
    <div class="col">
        <div class="page-pretitle">Cadre Section Reporting</div>
        <h2 class="page-title">{{ ($booklet ?? false) ? 'Booklet Printing' : 'Verification' }} PDF Export #{{ $run->id }}</h2>
        <div class="text-secondary">
            {{ strtoupper((string) $run->scope) }}
            · PDF
            · {{ data_get($run->parameters, 'page_size', 'Legal') }} {{ data_get($run->parameters, 'orientation', 'Landscape') }}
            · {{ data_get($run->parameters, 'margin_inches', 0.5) }} inch margin
        </div>
    </div>

    <div class="col-auto ms-auto">
        <a class="btn btn-outline-secondary" href="{{ route('examination-reports.cadre.index') }}">
            Back to Cadre Section
        </a>
    </div>
</div>
@endsection

@section('content')

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

@if($outdated)
    <div class="alert alert-warning">
        <strong>OUTDATED:</strong>
        A5/A5.5 publication authority changed after this PDF was generated. Regenerate the report.
    </div>
@endif

@php
    $isRunning = in_array($run->status, ['queued', 'running'], true);
@endphp

<div
    class="card"
    id="verification-export-progress"
    data-status-url="{{ route(($booklet ?? false) ? 'examination-reports.cadre.booklet.exports.status' : 'examination-reports.cadre.verification.exports.status', $run) }}"
    data-download-label="Download PDF"
>
    <div class="card-header">
        <h3 class="card-title">PDF Export Progress</h3>

        <div class="ms-auto">
            <span id="verification-export-status" class="badge bg-azure-lt">
                {{ $outdated ? 'OUTDATED' : strtoupper($run->status) }}
            </span>
        </div>
    </div>

    <div class="card-body">
        <div class="d-flex justify-content-between mb-2">
            <div>
                <div class="fw-bold" id="verification-export-phase">
                    {{ strtoupper(str_replace('_', ' ', (string) ($run->phase ?: $run->status))) }}
                </div>

                <div class="small text-secondary" id="verification-export-message">
                    {{ $run->progress_message }}
                </div>
            </div>

            <div class="fw-bold">
                <span id="verification-export-percent">{{ (int) $run->progress_percent }}</span>%
            </div>
        </div>

        <div class="progress progress-lg mb-2">
            <div
                id="verification-export-bar"
                class="progress-bar{{ $isRunning ? ' progress-bar-striped progress-bar-animated' : '' }}"
                style="width: {{ (int) $run->progress_percent }}%"
            ></div>
        </div>

        <div class="small text-secondary" id="verification-export-count">
            @if((int) $run->progress_total > 0)
                {{ number_format($run->progress_current) }}
                of
                {{ number_format($run->progress_total) }}
                report rows prepared
            @endif
        </div>

        <div
            id="verification-export-error"
            class="alert alert-danger mt-3{{ $run->status === 'failed' ? '' : ' d-none' }}"
        >
            {{ $run->failure_message }}
        </div>

        <div class="mt-3" id="verification-export-download-wrap">
            @if($run->status === 'completed' && ! $outdated)
                <a
                    class="btn btn-success"
                    href="{{ route(($booklet ?? false) ? 'examination-reports.cadre.booklet.exports.download' : 'examination-reports.cadre.verification.exports.download', $run) }}"
                >
                    Download PDF
                </a>
            @endif
        </div>
    </div>
</div>

@if($isRunning)
<script>
(() => {
    const box = document.getElementById('verification-export-progress');
    if (!box) {
        return;
    }

    const poll = async () => {
        try {
            const response = await fetch(box.dataset.statusUrl, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                throw new Error('Unable to read PDF export status.');
            }

            const data = await response.json();
            const percent = Math.max(0, Math.min(100, Number(data.progress_percent || 0)));

            document.getElementById('verification-export-percent').textContent = percent;
            document.getElementById('verification-export-bar').style.width = percent + '%';
            document.getElementById('verification-export-phase').textContent =
                String(data.phase || data.status || '').replaceAll('_', ' ').toUpperCase();
            document.getElementById('verification-export-message').textContent =
                data.progress_message || '';
            document.getElementById('verification-export-status').textContent =
                String(data.status || '').toUpperCase();

            document.getElementById('verification-export-count').textContent =
                Number(data.progress_total || 0) > 0
                    ? Number(data.progress_current || 0).toLocaleString()
                        + ' of '
                        + Number(data.progress_total || 0).toLocaleString()
                        + ' report rows prepared'
                    : '';

            if (data.failure_message) {
                const errorBox = document.getElementById('verification-export-error');
                errorBox.textContent = data.failure_message;
                errorBox.classList.remove('d-none');
            }

            if (data.download_url) {
                const link = document.createElement('a');
                link.className = 'btn btn-success';
                link.href = data.download_url;
                link.textContent = box.dataset.downloadLabel;

                document
                    .getElementById('verification-export-download-wrap')
                    .replaceChildren(link);
            }

            if (!data.finished) {
                window.setTimeout(poll, 1500);
            } else {
                document
                    .getElementById('verification-export-bar')
                    .classList.remove('progress-bar-striped', 'progress-bar-animated');
            }
        } catch (error) {
            window.setTimeout(poll, 3500);
        }
    };

    window.setTimeout(poll, 800);
})();
</script>
@endif

@endsection
