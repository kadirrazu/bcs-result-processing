@extends('layouts.app')

@section('content')
@php
    $running = in_array((string)$state->status, ['historical_optimization_queued','historical_optimizing','finalization_queued','finalizing'], true);
    $canFinalize = (string)$state->status === 'historical_optimized' && !$state->is_stale;
    $finalizing = in_array((string)$state->status, ['finalization_queued','finalizing'], true);
@endphp

<div class="page-header d-print-none">
    <div class="container-xl">
        <div class="row align-items-center g-2">
            <div class="col">
                <h2 class="page-title">Historical Choice Optimization</h2>
                <div class="text-secondary mt-1">Confirmed Previous BCS recommendations → Allocation-ready Choice</div>
            </div>
            <div class="col-auto ms-auto d-flex gap-2">
                @if(!$running)
                    <form method="POST" action="{{ route('choice-optimization.historical-choices.process') }}" class="mb-0">
                        @csrf
                        <button class="btn btn-outline-primary" type="submit">
                            {{ $rows->total() > 0 ? 'Re-process' : 'Process' }}
                        </button>
                    </form>
                @endif
                @if($canFinalize)
                    <form method="POST" action="{{ route('choice-optimization.historical-choices.finalize') }}" class="mb-0">
                        @csrf
                        <button class="btn btn-success" type="submit">Finalize Allocation-ready Choice</button>
                    </form>
                @endif
                <a class="btn btn-outline-secondary" href="{{ route('choice-optimization.index') }}">Back</a>
            </div>
        </div>
    </div>
</div>

<div class="page-body">
<div class="container-xl">

    @if((string)$state->status === 'finalization_failed')
        <div class="alert alert-danger">
            <strong>Finalization failed:</strong> {{ $state->stale_reason ?: 'Unknown finalization error.' }}
        </div>
    @elseif($state->is_stale && $state->stale_reason)
        <div class="alert alert-warning"><strong>STALE:</strong> {{ $state->stale_reason }}</div>
    @endif

    <div id="hist-choice-progress" class="card mb-3" @if(!$running) style="display:none" @endif>
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <strong>{{ $finalizing ? 'Finalizing Allocation-ready Choice' : 'Historical Choice Optimization is running' }}</strong>
                    <div class="small text-secondary">
                        Queue-based processing · this page updates automatically when finished.
                    </div>
                </div>
                <span class="spinner-border spinner-border-sm"></span>
            </div>
            <div class="progress progress-sm mt-3">
                <div class="progress-bar progress-bar-indeterminate"></div>
            </div>
        </div>
    </div>

    <div class="row row-cards mb-3">
        @foreach([
            ['Total', $summary['total_candidates'] ?? 0],
            ['Optimized', $summary['optimized_candidates'] ?? 0],
            ['Unchanged', $summary['unchanged_candidates'] ?? 0],
            ['No Higher Choice', $summary['no_higher_choice_candidates'] ?? 0],
            ['Blocking', $summary['blocking_candidates'] ?? 0],
            ['Warning', $summary['warning_candidates'] ?? 0],
        ] as [$label,$value])
            <div class="col-sm-6 col-lg-2">
                <div class="card h-100"><div class="card-body py-3">
                    <div class="text-secondary small">{{ $label }}</div>
                    <div class="h3 mb-0">{{ number_format((int)$value) }}</div>
                </div></div>
            </div>
        @endforeach
    </div>

    <div class="card mb-3">
        <div class="card-body py-3">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        @foreach([
                            'all'=>'All',
                            'OPTIMIZED'=>'Optimized',
                            'UNCHANGED'=>'Unchanged',
                            'NO_HIGHER_CHOICE_REMAINS'=>'No Higher Choice Remains',
                            'warning'=>'Warning',
                            'blocking'=>'Blocking',
                        ] as $key=>$label)
                            <option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Registration</label>
                    <input class="form-control" name="search" value="{{ $search }}" placeholder="Search registration">
                </div>
                <div class="col-md-auto">
                    <button class="btn btn-outline-primary">Apply</button>
                    <a class="btn btn-outline-secondary" href="{{ route('choice-optimization.historical-choices.index') }}">Clear</a>
                </div>
            </form>
        </div>
    </div>

    <div class="d-flex flex-column gap-3">
        @forelse($rows as $row)
            @php
                $statusBadge = match($row->optimization_status) {
                    'OPTIMIZED' => 'bg-blue-lt',
                    'NO_HIGHER_CHOICE_REMAINS' => 'bg-yellow-lt',
                    default => 'bg-green-lt',
                };
                $recommendations = (array)$row->historical_recommendations;
            @endphp

            <div class="card">
                <div class="card-body">
                    <div class="row g-3 align-items-start mb-3">
                        <div class="col-md-2">
                            <div class="text-secondary small">SL</div>
                            <div class="h3 mb-0">{{ ($rows->firstItem() ?? 1) + $loop->index }}</div>
                        </div>

                        <div class="col-md-4">
                            <div class="text-secondary small">Current Candidate</div>
                            <div><strong>Reg:</strong> <code>{{ $row->reg }}</code></div>
                            <div class="fw-semibold">{{ $row->registration?->name ?: '—' }}</div>
                        </div>

                        <div class="col-md-4">
                            <div class="text-secondary small">Previous BCS Match</div>
                            @forelse($recommendations as $rec)
                                <div class="mb-1">
                                    <span class="badge bg-blue-lt">
                                        BCS {{ $rec['bcs_number'] ?? '—' }} - {{ $rec['cadre'] ?? '—' }}
                                    </span>
                                    @if(!empty($rec['previous_reg']))
                                        <span class="small text-secondary ms-1">Reg: {{ $rec['previous_reg'] }}</span>
                                    @endif
                                </div>
                            @empty
                                <span class="badge bg-red-lt">NO PREVIOUS BCS MATCH</span>
                            @endforelse
                        </div>

                        <div class="col-md-2 text-md-end">
                            <div class="text-secondary small">System Status</div>
                            <span class="badge {{ $statusBadge }} text-wrap text-break d-inline-block"
                                  style="white-space:normal; max-width:100%; line-height:1.2">
                                {{ $row->optimization_status }}
                            </span>
                            @if($row->warnings)
                                <span class="badge bg-yellow-lt mt-1">WARNING</span>
                            @endif
                            @if($row->blocking_issues)
                                <span class="badge bg-red-lt mt-1">BLOCKING</span>
                            @endif
                            @if(empty($recommendations))
                                <span class="badge bg-red-lt mt-1">NO HISTORICAL DATA</span>
                            @endif
                        </div>
                    </div>

                    <div class="border-top pt-3">
                        <div class="mb-3">
                            <div class="small fw-semibold text-secondary mb-2">Input Effective Choice</div>
                            @include('choice-optimization.partials.choice-code-lane', [
                                'codes' => (array)$row->input_choice_codes,
                                'badgeClass' => 'bg-secondary-lt',
                                'emptyText' => 'No input choice',
                            ])
                        </div>

                        <div class="mb-3">
                            <div class="small fw-semibold text-success mb-2">Allocation-ready Choice</div>
                            @include('choice-optimization.partials.choice-code-lane', [
                                'codes' => (array)$row->final_choice_codes,
                                'badgeClass' => 'bg-green-lt',
                                'emptyText' => 'EMPTY',
                            ])
                        </div>

                        <div class="row g-3 align-items-end">
                            <div class="col-md-8">
                                @if($row->matched_cutoff)
                                    <div class="small">
                                        <strong>Cutoff:</strong>
                                        #{{ str_pad((string)($row->matched_cutoff['choice_position'] ?? 0),2,'0',STR_PAD_LEFT) }}
                                        · <code>{{ $row->matched_cutoff['choice_code'] ?? '—' }}</code>
                                        · BCS {{ $row->matched_cutoff['historical_bcs_number'] ?? '—' }}
                                        - {{ $row->matched_cutoff['historical_cadre'] ?? '—' }}
                                    </div>
                                    <div class="small text-secondary">
                                        Removed: {{ implode(', ', (array)$row->removed_choice_codes) ?: '—' }}
                                    </div>
                                @elseif(empty($recommendations))
                                    <span class="text-secondary small">No cutoff</span>
                                @else
                                    <div class="small text-secondary">No matching cutoff found in current effective choice.</div>
                                @endif
                            </div>
                            <div class="col-md-4 text-md-end">
                                <a class="btn btn-sm btn-outline-primary" href="{{ route('choice-optimization.historical-choices.show',$row) }}">View Detail</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="card">
                <div class="card-body text-center text-secondary py-5">
                    No Historical Choice Optimization output yet.
                </div>
            </div>
        @endforelse

        @if($rows->hasPages())
            <div class="card">
                <div class="card-footer">{{ $rows->links() }}</div>
            </div>
        @endif
    </div>
</div>
</div>

<script>
(() => {
    let active = @json($running);
    if(!active) return;

    const url = @json(route('choice-optimization.historical-choices.status'));

    const poll = async () => {
        if(!active) return;
        try {
            const response = await fetch(url,{headers:{'Accept':'application/json'},cache:'no-store'});
            if(!response.ok) return;
            const data = await response.json();
            const wasActive = active;
            active = !!data.running;

            if(wasActive && !active){
                window.setTimeout(()=>window.location.replace(window.location.href),250);
            }
        } catch (_) {}
    };

    poll();
    window.setInterval(poll,1500);
})();
</script>
@endsection
