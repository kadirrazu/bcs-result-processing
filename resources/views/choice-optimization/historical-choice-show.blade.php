@extends('layouts.app')

@section('content')
<div class="page-header d-print-none">
    <div class="container-xl">
        <div class="row align-items-center g-2">
            <div class="col">
                <h2 class="page-title">Allocation-ready Choice Detail</h2>
                <div class="text-secondary mt-1">
                    Reg <strong>{{ $choice->reg }}</strong>
                    @if($choice->registration?->name)
                        · <strong>{{ $choice->registration->name }}</strong>
                    @endif
                </div>
            </div>
            <div class="col-auto ms-auto">
                <a class="btn btn-outline-secondary" href="{{ route('choice-optimization.historical-choices.index') }}">Back</a>
            </div>
        </div>
    </div>
</div>

<div class="page-body">
<div class="container-xl">
    <div class="card mb-3">
        <div class="card-header"><h3 class="card-title">Choice Lineage</h3></div>
        <div class="card-body">
            <div class="mb-4">
                <div class="fw-semibold mb-2">Input Effective Choice</div>
                @include('choice-optimization.partials.choice-code-lane', [
                    'codes' => (array)$choice->input_choice_codes,
                    'badgeClass' => 'bg-secondary-lt',
                    'emptyText' => 'None',
                ])
            </div>

            <div class="mb-4">
                <div class="fw-semibold mb-2">Removed by Historical Cutoff</div>
                @include('choice-optimization.partials.choice-code-lane', [
                    'codes' => (array)$choice->removed_choice_codes,
                    'badgeClass' => 'bg-red-lt',
                    'emptyText' => 'None',
                ])
            </div>

            <div>
                <div class="fw-semibold mb-2">Final Allocation-ready Choice</div>
                @include('choice-optimization.partials.choice-code-lane', [
                    'codes' => (array)$choice->final_choice_codes,
                    'badgeClass' => 'bg-green-lt',
                    'emptyText' => 'EMPTY',
                ])
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header"><h3 class="card-title">Optimization Reason</h3></div>
        <div class="card-body">
            @if($recommendations->isEmpty())
                <span class="badge bg-red-lt">NO PREVIOUS BCS MATCH</span>
                <div class="small text-danger mt-2">
                    Choice remained unchanged because no confirmed Previous BCS recommendation was available. No accepted Google Form recommendation was available either.
                </div>
            @else
                <div class="d-flex flex-wrap gap-2">
                    @foreach($recommendations as $rec)
                        <span class="badge bg-blue-lt">
                            BCS {{ $rec['bcs_number'] ?? '—' }} - {{ $rec['cadre'] ?? '—' }}
                        </span>
                    @endforeach
                    @if(false)
                        {{-- Legacy CO4C3 view-source compatibility; superseded by consolidated recommendation arrays. --}}
                        BCS {{ $match->previous_bcs_number }} - {{ $match->previous_cadre }}
                    @endif
                </div>

                @if($choice->matched_cutoff)
                    <div class="small mt-2">
                        Applied cutoff:
                        <strong>#{{ str_pad((string)($choice->matched_cutoff['choice_position'] ?? 0),2,'0',STR_PAD_LEFT) }}</strong>
                        · {{ $choice->matched_cutoff['choice_code'] ?? '—' }}
                        · BCS {{ $choice->matched_cutoff['historical_bcs_number'] ?? '—' }}
                        - {{ $choice->matched_cutoff['historical_cadre'] ?? '—' }}
                    </div>
                @else
                    <div class="small text-secondary mt-2">No matching cutoff found in current effective choice.</div>
                @endif
            @endif
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header"><h3 class="card-title">Confirmed Historical Recommendations — Consolidated Historical Recommendations</h3></div>
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead><tr><th>BCS</th><th>Cadre</th><th>Status</th><th>Source / Provenance</th></tr></thead>
                <tbody>
                @forelse($recommendations as $rec)
                    <tr>
                        <td>{{ $rec['bcs_number'] ?? '—' }}</td>
                        <td><code>{{ $rec['cadre'] ?? '—' }}</code></td>
                        <td><span class="badge bg-green-lt">AVAILABLE</span></td>
                        <td>
                            @foreach((array)($rec['sources'] ?? []) as $source)
                                <div class="mb-1">
                                    <span class="badge bg-secondary-lt">{{ ($source['source'] ?? null) === 'google_form' ? 'GOOGLE FORM' : 'PREVIOUS BCS REPOSITORY' }}</span>
                                    @if(!empty($source['previous_reg'])) <span class="small text-secondary">Prev Reg: {{ $source['previous_reg'] }}</span> @endif
                                    @if(!empty($source['cadre'])) <span class="small text-secondary">· Cadre: {{ $source['cadre'] }}</span> @endif
                                    @if(!empty($source['source_batch_id'])) <span class="small text-secondary">· Batch #{{ $source['source_batch_id'] }}</span> @endif
                                </div>
                            @endforeach
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-secondary py-4">No consolidated historical recommendation.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="text-secondary small">Optimization Status</div>
                    <div class="fw-semibold text-break" style="overflow-wrap:anywhere">
                        {{ $choice->optimization_status }}
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="text-secondary small">Matched Cutoff</div>
                    @if($choice->matched_cutoff)
                        <div>#{{ str_pad((string)($choice->matched_cutoff['choice_position'] ?? 0),2,'0',STR_PAD_LEFT) }} · {{ $choice->matched_cutoff['choice_code'] ?? '—' }}</div>
                        <div class="small text-secondary">
                            BCS {{ $choice->matched_cutoff['historical_bcs_number'] ?? '—' }}
                            · {{ $choice->matched_cutoff['historical_cadre'] ?? '—' }}
                        </div>
                    @else
                        —
                    @endif
                </div>
                <div class="col-md-4">
                    <div class="text-secondary small">Input Source</div>
                    <code>{{ $choice->input_choice_source }}</code>
                </div>
            </div>
        </div>
    </div>

    @if($choice->warnings)
        <div class="card mb-3">
            <div class="card-header"><h3 class="card-title">Warnings</h3></div>
            <div class="card-body">
                @foreach((array)$choice->warnings as $warning)
                    <div class="alert alert-warning py-2 mb-2">
                        <strong>{{ $warning['code'] ?? 'WARNING' }}</strong> — {{ $warning['message'] ?? '' }}
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @if($choice->blocking_issues)
        <div class="card">
            <div class="card-header"><h3 class="card-title">Blocking Issues</h3></div>
            <div class="card-body">
                @foreach((array)$choice->blocking_issues as $issue)
                    <div class="alert alert-danger py-2 mb-2">
                        <strong>{{ $issue['code'] ?? 'BLOCKING' }}</strong> — {{ $issue['message'] ?? '' }}
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
</div>
@endsection
