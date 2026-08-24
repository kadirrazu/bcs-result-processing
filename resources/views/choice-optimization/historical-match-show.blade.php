@extends('layouts.app')

@section('content')
@php
    $registration = $match->registration;
    $evidence = (array) $match->match_evidence;
    $core = (array) data_get($evidence, 'core', []);
    $supporting = (array) data_get($evidence, 'supporting', []);

    $statusClass = fn (string $status) => match($status) {
        'exact', 'match' => 'bg-green-lt',
        'partial', 'not_compared' => 'bg-yellow-lt',
        'different', 'mismatch' => 'bg-red-lt',
        default => 'bg-secondary-lt',
    };
@endphp

<div class="page-header d-print-none">
    <div class="container-xl">
        <div class="row align-items-center g-2">
            <div class="col">
                <h2 class="page-title">Historical Match Review</h2>
                <div class="text-secondary mt-1">
                    BCS {{ $source->previous_bcs_number }} · Current Reg {{ $match->current_reg }} · Previous Reg {{ $match->previous_reg ?: '—' }}
                </div>
            </div>
            <div class="col-auto ms-auto d-flex gap-2">
                @if($nextReviewMatch && $match->match_status !== 'review')
                    <a class="btn btn-warning" href="{{ route('choice-optimization.historical.matches.show', [$source, $nextReviewMatch]) }}">Next Review</a>
                @endif
                <a class="btn btn-outline-secondary" href="{{ route('choice-optimization.historical.show', $source) }}">Back to Matches</a>
            </div>
        </div>
    </div>
</div>

<div class="page-body">
<div class="container-xl">

    <div class="card mb-3">
        <div class="card-header">
            <div>
                <h3 class="card-title">Candidate Comparison</h3>
                <div class="card-subtitle">Primary identity matched. Review supporting identity evidence before confirming a REVIEW record.</div>
            </div>
            <div class="ms-auto">
                @php
                    $mainBadge = match($match->match_status) {
                        'matched' => 'bg-green-lt',
                        'review' => 'bg-yellow-lt',
                        'rejected' => 'bg-red-lt',
                        default => 'bg-secondary-lt',
                    };
                @endphp
                <span class="badge {{ $mainBadge }}">{{ strtoupper($match->match_status) }}</span>
                @if($match->resolution_status === 'operator_confirmed')
                    <span class="badge bg-blue-lt ms-1">OPERATOR CONFIRMED</span>
                @elseif($match->resolution_status === 'auto_confirmed')
                    <span class="badge bg-green-lt ms-1">AUTO CONFIRMED</span>
                @elseif(in_array($match->resolution_status, ['operator_rejected','competing_rejected'], true))
                    <span class="badge bg-red-lt ms-1">REJECTED</span>
                @endif
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                <tr>
                    <th>Field</th>
                    <th>Current Candidate <strong>(BCS {{ $currentBcsNumber ?: '—' }})</strong></th>
                    <th>Previous BCS Record <strong>(BCS {{ $source->previous_bcs_number }})</strong></th>
                    <th>Evidence</th>
                </tr>
                </thead>
                <tbody>
                @foreach([
                    ['Registration', $match->current_reg, $match->previous_reg, null],
                    ['Name', $registration?->name, $match->previous_name, data_get($supporting, 'name.status')],
                    ['Father Name', $registration?->father_name, $match->previous_fname, data_get($supporting, 'father_name.status')],
                    ['Mother Name', $registration?->mother_name, $match->previous_mname, data_get($supporting, 'mother_name.status')],
                    ['SSC Roll', $registration?->ssc_roll, data_get($core, 'ssc_roll.previous'), data_get($core, 'ssc_roll.match') ? 'match' : 'mismatch'],
                    ['SSC Year', $registration?->ssc_year, data_get($core, 'ssc_year.previous'), data_get($core, 'ssc_year.match') ? 'match' : 'mismatch'],
                    ['Primary DOB', $registration?->birth_date?->format('d-m-Y'), data_get($core, 'birth_date.previous'), data_get($core, 'birth_date.match') ? 'match' : 'mismatch'],
                    ['HSC Roll', $registration?->hsc_roll, data_get($supporting, 'hsc_roll.previous'), data_get($supporting, 'hsc_roll.status')],
                    ['HSC Year', $registration?->hsc_year, data_get($supporting, 'hsc_year.previous'), data_get($supporting, 'hsc_year.status')],
                    ['NID', $registration?->national_id, data_get($supporting, 'nid.previous'), data_get($supporting, 'nid.status')],
                    ['Secondary DOB', $registration?->birth_date?->format('d-m-Y'), data_get($supporting, 'secondary_dob.previous'), data_get($supporting, 'secondary_dob.status')],
                    [
                        'District',
                        filled($registration?->district_code)
                            ? ((string) $registration->district_code).' - '.($currentDistrict?->name ?: 'Unresolved')
                            : '—',
                        data_get($supporting, 'district.previous_name'),
                        'context'
                    ],
                    ['Recommended Cadre', '—', $match->previous_cadre, 'historical'],
                ] as [$field,$current,$previous,$evidenceStatus])
                    <tr>
                        <td class="fw-semibold">{{ $field }}</td>
                        <td>{{ filled($current) ? $current : '—' }}</td>
                        <td>{{ filled($previous) ? $previous : '—' }}</td>
                        <td>
                            @if($evidenceStatus)
                                <span class="badge {{ $statusClass((string)$evidenceStatus) }}">
                                    {{ strtoupper(str_replace('_',' ',(string)$evidenceStatus)) }}
                                </span>
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="text-secondary small">Match Method</div>
                    <code>{{ $match->match_method }}</code>
                </div>
                <div class="col-md-4">
                    <div class="text-secondary small">Repository Dataset</div>
                    <div>v{{ $source->repository_dataset_version }} · Row #{{ $match->repository_row_id }}</div>
                </div>
                <div class="col-md-4">
                    <div class="text-secondary small">Resolution</div>
                    <div>{{ strtoupper(str_replace('_',' ',(string)($match->resolution_status ?: 'pending'))) }}</div>
                </div>
            </div>
        </div>
    </div>

    @if($match->match_status === 'review' && $match->resolution_status === 'pending')
        <div class="card" id="historical-review-card">
            <div class="card-header">
                <div>
                    <h3 class="card-title">Operator Decision</h3>
                    <div class="card-subtitle">Confirm only when the Previous BCS record belongs to this current candidate. Administrative reason is mandatory.</div>
                </div>
            </div>
            <div class="card-body">
                <form method="POST"
                      action="{{ route('choice-optimization.historical.matches.resolve', [$source, $match]) }}"
                      id="historical-review-form">
                    @csrf
                    <div class="row g-2 mb-3">
                        <div class="col-lg-6">
                            <label class="border rounded p-3 d-block h-100">
                                <input class="form-check-input me-2" type="radio" name="decision" value="confirm" required>
                                <strong>Confirm Match</strong>
                                <div class="small text-secondary mt-1">Use this historical recommendation for the current candidate.</div>
                            </label>
                        </div>
                        <div class="col-lg-6">
                            <label class="border rounded p-3 d-block h-100">
                                <input class="form-check-input me-2" type="radio" name="decision" value="reject" required>
                                <strong>Reject Match</strong>
                                <div class="small text-secondary mt-1">Do not incorporate this historical record for the current candidate.</div>
                            </label>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Administrative reason <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="reason" rows="3" maxlength="2000" required></textarea>
                    </div>

                    <div class="d-flex gap-2 align-items-center">
                        <button class="btn btn-primary" type="submit">Save & Continue</button>
                        <span class="small text-secondary" id="historical-review-state"></span>
                    </div>
                </form>
            </div>
        </div>
    @elseif($match->resolution_status && $match->resolution_status !== 'auto_confirmed')
        <div class="card">
            <div class="card-header"><h3 class="card-title">Operator Resolution Audit</h3></div>
            <div class="card-body">
                <div class="mb-2"><strong>{{ strtoupper(str_replace('_',' ',$match->resolution_status)) }}</strong></div>
                <div>{{ $match->resolution_reason ?: '—' }}</div>
                <div class="small text-secondary mt-2">
                    @if($match->resolution_status === 'operator_confirmed')
                        Confirmed by:
                    @elseif($match->resolution_status === 'operator_rejected')
                        Rejected by:
                    @else
                        Resolved by:
                    @endif
                    User #{{ $match->resolved_by ?: '—' }}
                    @if($resolvedByUser)
                        ({{ $resolvedByUser->name }})
                    @endif
                    @if($match->resolved_at) · {{ $match->resolved_at->format('d M Y, h:i A') }} @endif
                </div>
            </div>
        </div>
    @endif

</div>
</div>

@if($match->match_status === 'review' && $match->resolution_status === 'pending')
<script>
(() => {
    const form = document.getElementById('historical-review-form');
    const state = document.getElementById('historical-review-state');

    form?.addEventListener('submit', async (event) => {
        event.preventDefault();
        if(!form.reportValidity()) return;

        if(state) state.textContent = 'Saving…';

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: {
                    'Accept':'application/json',
                    'X-Requested-With':'XMLHttpRequest',
                },
            });

            const data = await response.json().catch(() => ({}));
            if(!response.ok) throw new Error(data.message || 'Unable to save Historical Match review.');

            if(state) state.textContent = data.message || 'Saved';
            window.setTimeout(() => {
                window.location.href = data.next_review_url || data.source_url;
            }, 220);
        } catch(error) {
            if(state) {
                state.textContent = error.message;
                state.classList.add('text-danger');
            }
        }
    });
})();
</script>
@endif
@endsection
