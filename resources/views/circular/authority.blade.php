@extends('layouts.app')

@section('title', 'Circular Authority Preview & Finalization')

@section('content')
<div class="page-header d-print-none">
    <div class="container-xl">
        <div class="row align-items-center">
            <div class="col">
                <h2 class="page-title">Authority Preview &amp; Finalization</h2>
                <div class="text-secondary">
                    Version {{ $state->current_version }} · {{ $state->status->label() }} ·
                    Preview, confirm and finalize the exact approved Circular dataset.
                </div>
            </div>
            <div class="col-auto ms-auto d-flex flex-wrap gap-2">
                <a class="btn btn-outline-secondary" href="{{ route('circular.index') }}">Workspace</a>
                <a class="btn btn-outline-primary" href="{{ route('circular.view') }}">Circular View</a>
                <a class="btn btn-outline-secondary" href="{{ route('circular.history') }}">Version &amp; Audit History</a>
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

        <div class="row row-cards mb-3">
            <div class="col-lg-7">
                <div class="card h-100">
                    <div class="card-header">
                        <div>
                            <h3 class="card-title">Current Authority Workflow</h3>
                            <div class="text-secondary small">
                                The PDF is tied to a SHA-256 hash of the exact Circular version and eligibility rows.
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <dl class="row mb-3">
                            <dt class="col-5">Current version</dt>
                            <dd class="col-7">{{ $state->current_version }}</dd>
                            <dt class="col-5">Approved version</dt>
                            <dd class="col-7">{{ $state->approved_version ?? '—' }}</dd>
                            <dt class="col-5">Confirmed version</dt>
                            <dd class="col-7">{{ $state->confirmed_version ?? '—' }}</dd>
                            <dt class="col-5">Finalized version</dt>
                            <dd class="col-7">{{ $state->finalized_version ?? '—' }}</dd>
                            <dt class="col-5">Dataset hash</dt>
                            <dd class="col-7"><code class="text-break">{{ $currentHash ?: '—' }}</code></dd>
                        </dl>

                        @if (
                            (int) $state->approved_version === (int) $state->current_version
                            && (int) $state->current_version > 0
                            && $state->status->value !== 'finalized'
                        )
                            <form method="POST" action="{{ route('circular.authority.generate') }}">
                                @csrf
                                <button class="btn btn-primary">Generate New Authority Preview PDF</button>
                            </form>
                        @else
                            <div class="alert alert-info mb-0">
                                The current Circular must be approved/effective before an Authority Preview can be generated.
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card h-100">
                    <div class="card-header">
                        <h3 class="card-title">Finalization Gate</h3>
                    </div>
                    <div class="card-body">
                        @if (
                            $state->status->value === 'confirmed'
                            && (int) $state->confirmed_version === (int) $state->current_version
                        )
                            <p>
                                The current version is confirmed against an Authority Preview.
                                Finalization will make this the authoritative version for downstream eligibility processing.
                            </p>
                            <form method="POST" action="{{ route('circular.authority.finalize') }}">
                                @csrf
                                <button
                                    class="btn btn-success"
                                    onclick="return confirm('Finalize this Circular version?')"
                                >
                                    Finalize Circular Version {{ $state->current_version }}
                                </button>
                            </form>
                        @elseif ($state->status->value === 'finalized')
                            <div class="alert alert-success mb-0">
                                <strong>Finalized.</strong>
                                Version {{ $state->finalized_version }} is the authoritative downstream Circular dataset.
                            </div>
                        @else
                            <div class="text-secondary">
                                Finalization remains locked until the current approved version has a current Authority Preview
                                and explicit confirmation.
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">
                <div>
                    <h3 class="card-title">Authority Preview History</h3>
                    <div class="text-secondary small">
                        Every generated preview is retained. A later preview never silently overwrites an earlier one.
                    </div>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-vcenter mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Version</th>
                            <th>Generated</th>
                            <th>Dataset Hash</th>
                            <th>Summary</th>
                            <th>Confirmation</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($previews as $preview)
                            @php
                                $matchesCurrent = (int) $preview->version === (int) $state->current_version
                                    && (int) $state->approved_version === (int) $state->current_version
                                    && hash_equals($preview->dataset_hash, $currentHash);
                            @endphp
                            <tr>
                                <td>#{{ $preview->id }}</td>
                                <td>v{{ $preview->version }}</td>
                                <td>{{ optional($preview->generated_at)->format('d M Y h:i A') }}</td>
                                <td><code>{{ substr($preview->dataset_hash, 0, 14) }}…</code></td>
                                <td>
                                    {{ data_get($preview->summary, 'entries', 0) }} entries ·
                                    {{ number_format((int) data_get($preview->summary, 'active_posts', 0)) }} posts
                                </td>
                                <td>
                                    @if ($preview->confirmations->isNotEmpty())
                                        @php($latestConfirmation = $preview->confirmations->sortByDesc('confirmed_at')->first())
                                        <span class="badge bg-green-lt text-green">Confirmed</span>
                                        <div class="small text-secondary mt-1">
                                            {{ optional($latestConfirmation->confirmed_at)->format('d M Y h:i A') }}
                                        </div>
                                    @else
                                        <span class="badge bg-secondary-lt text-secondary">Not confirmed</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="d-flex flex-wrap gap-2">
                                        <a
                                            class="btn btn-sm btn-outline-primary"
                                            href="{{ route('circular.authority.download', $preview) }}"
                                        >PDF</a>
                                        @if (
                                            $matchesCurrent
                                            && $preview->confirmations->isEmpty()
                                            && $state->status->value !== 'finalized'
                                        )
                                            <button
                                                class="btn btn-sm btn-success"
                                                type="button"
                                                data-bs-toggle="collapse"
                                                data-bs-target="#confirm-preview-{{ $preview->id }}"
                                            >Confirm</button>
                                        @endif
                                    </div>
                                </td>
                            </tr>

                            @if (
                                $matchesCurrent
                                && $preview->confirmations->isEmpty()
                                && $state->status->value !== 'finalized'
                            )
                                <tr class="collapse" id="confirm-preview-{{ $preview->id }}">
                                    <td colspan="7">
                                        <form
                                            method="POST"
                                            action="{{ route('circular.authority.confirm', $preview) }}"
                                            class="row g-2 align-items-end"
                                        >
                                            @csrf
                                            <div class="col-md-9">
                                                <label class="form-label required">Authority confirmation notes</label>
                                                <textarea
                                                    name="confirmation_notes"
                                                    class="form-control"
                                                    rows="2"
                                                    minlength="3"
                                                    maxlength="5000"
                                                    required
                                                    placeholder="Record the authority/reviewer confirmation context and notes"
                                                ></textarea>
                                            </div>
                                            <div class="col-md-3">
                                                <button class="btn btn-success w-100">Confirm This Exact Preview</button>
                                            </div>
                                        </form>
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-secondary py-4">
                                    No Authority Preview has been generated yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="alert alert-warning">
            <strong>Non-regression rule:</strong>
            any later audited Circular change reopens the workflow as Draft and requires a new approval,
            Authority Preview, confirmation and finalization cycle. Previous previews and confirmations remain historical records.
        </div>
    </div>
</div>
@endsection
