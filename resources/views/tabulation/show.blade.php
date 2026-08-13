@extends('layouts.app')

@section('content')
@php
    $prelimStatus = strtoupper((string) ($preliminary?->result_status?->value ?? $preliminary?->result_status ?? ''));
    $generalWrittenStatus = strtoupper((string) ($written->general_result_status?->value ?? $written->general_result_status ?? ''));
    $technicalWrittenStatus = strtoupper((string) ($written->technical_result_status?->value ?? $written->technical_result_status ?? ''));
    $vivaStatus = strtoupper((string) ($viva->viva_result_status?->value ?? $viva->viva_result_status ?? ''));
    $generalPf = strtoupper((string) $result->general_pf);
    $technicalPf = strtoupper((string) $result->technical_pf);
    $registrationCategory = $registration->cadre_category
        ? ($registration->cadre_category->value.' - '.$registration->cadre_category->code())
        : '—';

    $statusBadge = static function (?string $status): string {
        return match (strtoupper((string) $status)) {
            'PASS' => 'bg-green-lt text-green',
            'FAIL' => 'bg-red-lt text-red',
            default => 'bg-secondary-lt text-secondary',
        };
    };
@endphp

<div class="page-header">
    <div class="container-xl">
        <div class="row align-items-center g-2">
            <div class="col">
                <h2 class="page-title">Individual Finalized Tabulation</h2>
                <div class="text-secondary">REG {{ $result->reg }} · Version {{ $result->processing_version }}</div>
            </div>
            <div class="col-auto d-flex gap-2">
                <a class="btn btn-outline-danger" href="{{ route('tabulation.pdf', $result) }}">Download PDF</a>
                <a class="btn btn-outline-secondary" href="{{ route('tabulation.results') }}">Back</a>
            </div>
        </div>
    </div>
</div>

<div class="page-body">
    <div class="container-xl">
        <div class="mb-4">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div>
                    <h3 class="mb-1">Upstream Finalized Data</h3>
                    <div class="text-secondary">Authoritative values read from the finalized upstream modules.</div>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-lg-6 d-flex">
                    <div class="card w-100 h-100">
                        <div class="card-header py-2">
                            <h4 class="card-title mb-0">Registration</h4>
                        </div>
                        <div class="list-group list-group-flush">
                            <div class="list-group-item py-2">
                                <div class="row align-items-center g-2">
                                    <div class="col-6 text-secondary">Name</div>
                                    <div class="col-6 fw-medium">{{ $registration->name }}</div>
                                </div>
                            </div>
                            <div class="list-group-item py-2">
                                <div class="row align-items-center g-2">
                                    <div class="col-6 text-secondary">Reg</div>
                                    <div class="col-6">{{ $registration->reg }}</div>
                                </div>
                            </div>
                            <div class="list-group-item py-2">
                                <div class="row align-items-center g-2">
                                    <div class="col-6 text-secondary">User</div>
                                    <div class="col-6">{{ $registration->user_id }}</div>
                                </div>
                            </div>
                            <div class="list-group-item py-2">
                                <div class="row align-items-center g-2">
                                    <div class="col-6 text-secondary">Registration Category</div>
                                    <div class="col-6">{{ $registrationCategory }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6 d-flex">
                    <div class="card w-100 h-100">
                        <div class="card-header py-2">
                            <h4 class="card-title mb-0">Preliminary</h4>
                        </div>
                        <div class="list-group list-group-flush">
                            <div class="list-group-item py-2">
                                <div class="row align-items-center g-2">
                                    <div class="col-6 text-secondary">Mark</div>
                                    <div class="col-6">{{ $preliminary?->mark ?? '—' }}</div>
                                </div>
                            </div>
                            <div class="list-group-item py-2">
                                <div class="row align-items-center g-2">
                                    <div class="col-6 text-secondary">Result</div>
                                    <div class="col-6">
                                        @if($prelimStatus !== '')
                                            <span class="badge {{ $statusBadge($prelimStatus) }}">{{ $prelimStatus }}</span>
                                        @else
                                            —
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6 d-flex">
                    <div class="card w-100 h-100">
                        <div class="card-header py-2">
                            <h4 class="card-title mb-0">Written</h4>
                        </div>
                        <div class="list-group list-group-flush">
                            <div class="list-group-item py-2">
                                <div class="row align-items-center g-2">
                                    <div class="col-6 text-secondary">Qualified Track</div>
                                    <div class="col-6 fw-medium">{{ strtoupper((string) ($written->written_qualified_track?->value ?? $written->written_qualified_track ?? '—')) }}</div>
                                </div>
                            </div>
                            <div class="list-group-item py-2">
                                <div class="row align-items-center g-2">
                                    <div class="col-6 text-secondary">General Counted</div>
                                    <div class="col-6">
                                        {{ number_format((float) $written->general_counted_total, 2) }}
                                        @if($generalWrittenStatus !== '')
                                            <span class="badge {{ $statusBadge($generalWrittenStatus) }} ms-1">{{ $generalWrittenStatus }}</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <div class="list-group-item py-2">
                                <div class="row align-items-center g-2">
                                    <div class="col-6 text-secondary">Technical Counted</div>
                                    <div class="col-6">
                                        {{ number_format((float) $written->technical_counted_total, 2) }}
                                        @if($technicalWrittenStatus !== '')
                                            <span class="badge {{ $statusBadge($technicalWrittenStatus) }} ms-1">{{ $technicalWrittenStatus }}</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6 d-flex">
                    <div class="card w-100 h-100">
                        <div class="card-header py-2">
                            <h4 class="card-title mb-0">Viva</h4>
                        </div>
                        <div class="list-group list-group-flush">
                            <div class="list-group-item py-2">
                                <div class="row align-items-center g-2">
                                    <div class="col-6 text-secondary">Attendance</div>
                                    <div class="col-6">{{ strtoupper((string) $viva->attendance_status) }}</div>
                                </div>
                            </div>
                            <div class="list-group-item py-2">
                                <div class="row align-items-center g-2">
                                    <div class="col-6 text-secondary">Mark</div>
                                    <div class="col-6">{{ $viva->mark }}</div>
                                </div>
                            </div>
                            <div class="list-group-item py-2">
                                <div class="row align-items-center g-2">
                                    <div class="col-6 text-secondary">Result</div>
                                    <div class="col-6">
                                        @if($vivaStatus !== '')
                                            <span class="badge {{ $statusBadge($vivaStatus) }}">{{ $vivaStatus }}</span>
                                        @else
                                            —
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">
                <div>
                    <h3 class="card-title">Source → Derived Verification</h3>
                    <div class="card-subtitle">Confirms that finalized upstream values were carried into Tabulation without manual alteration.</div>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-vcenter">
                    <thead>
                        <tr>
                            <th>Field</th>
                            <th>Upstream Finalized</th>
                            <th>Tabulation Derived/Carried</th>
                            <th>Verification</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($verificationRows as $row)
                            <tr>
                                <td>{{ $row['label'] }}</td>
                                <td>{{ $row['source'] ?? '—' }}</td>
                                <td>{{ $row['derived'] ?? '—' }}</td>
                                <td>
                                    <span class="badge bg-{{ $row['matches'] ? 'success' : 'danger' }}-lt">
                                        {{ $row['matches'] ? 'MATCH' : 'MISMATCH' }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h3 class="card-title">Derived Tabulation Data</h3></div>
            <div class="table-responsive">
                <table class="table table-vcenter mb-0">
                    <tbody>
                        <tr><th class="w-40">Cadre Category Snapshot</th><td>{{ $result->cadre_category ?? '—' }}</td></tr>
                        <tr><th>Written Qualified Track Snapshot</th><td><span class="badge bg-azure-lt text-azure">{{ strtoupper((string) ($result->written_qualified_track ?: '—')) }}</span></td></tr>
                        <tr><th>Birth Date Snapshot</th><td>{{ $result->birth_date?->format('Y-m-d') ?? '—' }}</td></tr>
                        <tr><th class="w-40">General Written / Technical Written</th><td>{{ $result->general_written_total ?? '—' }} / {{ $result->technical_written_total ?? '—' }}</td></tr>
                        <tr><th>Viva Mark</th><td>{{ $result->viva_mark }}</td></tr>
                        <tr><th>General / Technical Grand Total</th><td>{{ $result->generalGrandTotalDisplay() }} / {{ $result->technicalGrandTotalDisplay() }}</td></tr>
                        <tr>
                            <th>General / Technical P/F</th>
                            <td>
                                <span class="badge {{ $statusBadge($generalPf) }}">{{ $generalPf ?: '—' }}</span>
                                /
                                <span class="badge {{ $statusBadge($technicalPf) }}">{{ $technicalPf ?: '—' }}</span>
                            </td>
                        </tr>
                        <tr><th>General / Technical Merit Eligible</th><td>{{ $result->general_merit_eligible ? 'YES' : 'NO' }} / {{ $result->technical_merit_eligible ? 'YES' : 'NO' }}</td></tr>
                        <tr><th>Validation</th><td>{{ strtoupper((string) $result->validation_status) }}</td></tr>
                        <tr><th>Warnings</th><td>{{ implode(', ', (array) $result->review_warnings) ?: 'NONE' }}</td></tr>
                        <tr><th>Validation Errors</th><td>{{ implode(', ', (array) $result->validation_errors) ?: 'NONE' }}</td></tr>
                        <tr><th>Processed At</th><td>{{ $result->processed_at }}</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
