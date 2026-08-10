@extends('layouts.app')

@section('title', 'Circular Entry Details')

@section('content')
<div class="page-header d-print-none">
    <div class="container-xl">
        <div class="row align-items-center">
            <div class="col">
                <h2 class="page-title">Circular Entry Details</h2>
                <div class="text-secondary">
                    Version {{ $entry->version }} · Serial {{ $entry->cadre_serial }}
                    @if($entry->sub_serial)
                        .{{ $entry->sub_serial }}
                    @endif
                    · Effective code {{ $entry->effective_code }}
                </div>
            </div>
            <div class="col-auto ms-auto d-flex gap-2">
                <a class="btn btn-outline-secondary" href="{{ route('circular.entries.index', ['version' => $entry->version]) }}">Back to Listing</a>
                <a class="btn btn-outline-primary" href="{{ $isHistorical ? route('circular.versions.show', $entry->version) : route('circular.view') }}">Circular View</a>
                @unless($isHistorical)
                    <a class="btn btn-primary" href="{{ route('circular.entries.edit', $entry) }}">Edit Entry</a>
                @endunless
            </div>
        </div>
    </div>
</div>

<div class="page-body">
    <div class="container-xl">
        @if($isHistorical)
            <div class="alert alert-info">
                <strong>Read-only historical snapshot.</strong>
                This entry belongs to preserved version {{ $entry->version }} and cannot be edited.
            </div>
        @endif

        <div class="row row-cards mb-3">
            <div class="col-lg-7">
                <div class="card h-100">
                    <div class="card-header"><h3 class="card-title">Circular Identity Snapshot</h3></div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-sm-4">Cadre</dt>
                            <dd class="col-sm-8">
                                <div class="fw-semibold">{{ $entry->cadre_name_bn_snapshot ?: $entry->cadre_name_snapshot }}</div>
                                <div class="text-secondary">{{ $entry->cadre_name_snapshot }}</div>
                            </dd>

                            <dt class="col-sm-4">Post</dt>
                            <dd class="col-sm-8">
                                <div class="fw-semibold">{{ $entry->post_name_bn_snapshot ?: $entry->post_name_snapshot ?: '—' }}</div>
                                <div class="text-secondary">{{ $entry->post_name_snapshot ?: '—' }}</div>
                            </dd>

                            <dt class="col-sm-4">Parent cadre code</dt>
                            <dd class="col-sm-8">{{ $entry->cadre_code }}</dd>

                            <dt class="col-sm-4">Sub-cadre code</dt>
                            <dd class="col-sm-8">{{ $entry->sub_cadre_code ?? '—' }}</dd>

                            <dt class="col-sm-4">Effective code</dt>
                            <dd class="col-sm-8"><span class="badge bg-blue-lt text-blue">{{ $entry->effective_code }}</span></dd>

                            <dt class="col-sm-4">Cadre type</dt>
                            <dd class="col-sm-8"><span class="badge bg-azure-lt text-azure">{{ $entry->cadre_type->value }}</span></dd>

                            <dt class="col-sm-4">Vacant posts</dt>
                            <dd class="col-sm-8">{{ number_format($entry->post_count) }}</dd>

                            <dt class="col-sm-4">Status / Source</dt>
                            <dd class="col-sm-8">{{ strtoupper($entry->status) }} · {{ strtoupper($entry->source) }}</dd>

                            <dt class="col-sm-4">Note</dt>
                            <dd class="col-sm-8">{{ $entry->note ?: '—' }}</dd>
                        </dl>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card h-100">
                    <div class="card-header"><h3 class="card-title">Current Master Reference</h3></div>
                    <div class="card-body">
                        @if($masterCadre)
                            <div class="mb-3">
                                <div class="text-secondary small">Parent Cadre Master</div>
                                <div class="fw-semibold">{{ $masterCadre->cadre_name_bn ?: $masterCadre->cadre_name }}</div>
                                <div>{{ $masterCadre->cadre_name }}</div>
                                <div class="text-secondary small">
                                    Code {{ $masterCadre->cadre_code }} · {{ $masterCadre->cadre_abbr }} ·
                                    {{ $masterCadre->cadre_type->value ?? $masterCadre->cadre_type }}
                                </div>
                            </div>
                        @else
                            <div class="alert alert-warning">
                                Current parent Cadre Master record could not be resolved. The Circular snapshot is still preserved.
                            </div>
                        @endif

                        @if($entry->sub_cadre_code)
                            @if($masterSubCadre)
                                <hr>
                                <div>
                                    <div class="text-secondary small">Sub Cadre Master</div>
                                    <div class="fw-semibold">{{ $masterSubCadre->post_name_bn ?: $masterSubCadre->post_name }}</div>
                                    <div>{{ $masterSubCadre->post_name }}</div>
                                    <div class="text-secondary small">Code {{ $masterSubCadre->sub_cadre_code }} · {{ $masterSubCadre->sub_cadre_abbr }}</div>
                                </div>
                            @else
                                <hr>
                                <div class="alert alert-warning mb-0">
                                    Current Sub Cadre Master record could not be resolved. The Circular snapshot is still preserved.
                                </div>
                            @endif
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">
                <div>
                    <h3 class="card-title">Eligibility Viewer</h3>
                    <div class="text-secondary small">Finalized downstream eligibility uses the Circular's stored allowed-code sets, not the prose description in the source PDF.</div>
                </div>
            </div>
            <div class="card-body">
                @if($entry->cadre_type->value === 'GG')
                    <div class="alert alert-info mb-0">
                        <strong>General Cadre:</strong> Bachelor-subject and PRS restrictions do not apply to this Circular entry.
                    </div>
                @else
                    <div class="row g-3">
                        <div class="col-md-6">
                            <h4 class="mb-2">Allowed Bachelor Subjects</h4>
                            <div class="table-responsive">
                                <table class="table table-sm table-vcenter">
                                    <thead><tr><th>Code</th><th>Subject</th></tr></thead>
                                    <tbody>
                                        @forelse($entry->bachelorSubjects as $subject)
                                            <tr>
                                                <td class="fw-semibold">{{ $subject->subject_code }}</td>
                                                <td>{{ $bachelorMap[$subject->subject_code] ?? 'Unknown / inactive master reference' }}</td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="2" class="text-danger">No Bachelor Subject eligibility stored.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <h4 class="mb-2">Allowed Registration PRS</h4>
                            <div class="table-responsive">
                                <table class="table table-sm table-vcenter">
                                    <thead><tr><th>Code</th><th>Subject</th></tr></thead>
                                    <tbody>
                                        @forelse($entry->prsSubjects as $prs)
                                            <tr>
                                                <td class="fw-semibold">{{ $prs->prs_code }}</td>
                                                <td>{{ $prsMap[$prs->prs_code] ?? 'Unknown / inactive master reference' }}</td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="2" class="text-danger">No PRS eligibility stored.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-secondary mt-3 mb-0">
                        <strong>Eligibility semantics:</strong>
                        candidate Bachelor Subject must match one allowed Bachelor code <em>AND</em>
                        Registration PRS must match one allowed PRS code. Multiple values inside each set use OR/IN semantics.
                    </div>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h3 class="card-title">Version Metadata</h3></div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-3">Entry ID</dt>
                    <dd class="col-sm-9">{{ $entry->id }}</dd>

                    <dt class="col-sm-3">Circular version</dt>
                    <dd class="col-sm-9">
                        {{ $entry->version }}
                        @if((int) $entry->version === (int) $state->current_version)
                            <span class="badge bg-blue-lt text-blue">Current</span>
                        @else
                            <span class="badge bg-secondary-lt text-secondary">Historical</span>
                        @endif
                    </dd>

                    <dt class="col-sm-3">Created</dt>
                    <dd class="col-sm-9">{{ $entry->created_at?->format('d M Y, h:i:s A') ?? '—' }}</dd>

                    <dt class="col-sm-3">Last changed</dt>
                    <dd class="col-sm-9">{{ $entry->updated_at?->format('d M Y, h:i:s A') ?? '—' }}</dd>
                </dl>
            </div>
        </div>
    </div>
</div>
@endsection
