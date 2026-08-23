@extends('layouts.app')

@section('content')
<div class="page-header d-print-none">
    <div class="container-xl">
        <div class="row align-items-center g-2">
            <div class="col">
                <h2 class="page-title">Historical Previous BCS Match</h2>
                <div class="text-secondary mt-1">
                    BCS {{ $source->previous_bcs_number }} · Workspace snapshot from repository v{{ $source->repository_dataset_version }}
                </div>
            </div>
            <div class="col-auto ms-auto d-flex gap-2">
                @if($repository?->currentEffectiveDataset && !in_array($source->status, ['pull_queued','pulling'], true))
                    <form method="POST" action="{{ route('choice-optimization.historical.pull') }}" class="mb-0">
                        @csrf
                        <input type="hidden" name="bcs_numbers[]" value="{{ $source->previous_bcs_number }}">
                        <button class="btn btn-outline-primary" type="submit">Re-pull</button>
                    </form>
                @endif
                <a class="btn btn-outline-secondary" href="{{ route('choice-optimization.index') }}">Back</a>
            </div>
        </div>
    </div>
</div>

<div class="page-body">
<div class="container-xl">

    @if($updateAvailable)
        <div class="alert alert-warning">
            <strong>Update available.</strong>
            The central repository now has a newer EFFECTIVE dataset for BCS {{ $source->previous_bcs_number }}.
            Use Re-pull to replace this workspace snapshot.
        </div>
    @endif

    @if($source->status === 'failed')
        <div class="alert alert-danger">
            <strong>Pull failed:</strong> {{ $source->failure_message }}
        </div>
    @endif

    <div class="row row-cards mb-3">
        @foreach([
            ['Status', strtoupper(str_replace('_',' ', $source->status)), 'hist-status'],
            ['Candidates', number_format($source->candidate_count), 'hist-candidates'],
            ['Matched', number_format($source->matched_count), 'hist-matched'],
            ['Needs Review', number_format($source->review_count), 'hist-review'],
            ['No Match', number_format($source->no_match_count), 'hist-no-match'],
        ] as [$label,$value,$id])
            <div class="col-sm-6 col-lg">
                <div class="card h-100">
                    <div class="card-body py-3">
                        <div class="text-secondary small">{{ $label }}</div>
                        <div class="h3 mb-0" id="{{ $id }}">{{ $value }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div id="hist-progress" class="card mb-3" @if(!in_array($source->status,['pull_queued','pulling'],true)) style="display:none" @endif>
        <div class="card-body py-3">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <strong>Historical matching is running</strong>
                    <div class="small text-secondary">Queue-based processing · page updates automatically when finished.</div>
                </div>
                <span class="spinner-border spinner-border-sm" role="status"></span>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body py-3">
            <div class="row g-3">
                <div class="col-md-3">
                    <div class="text-secondary small">Pulled Repository Version</div>
                    <div class="fw-semibold">v{{ $source->repository_dataset_version }}</div>
                </div>
                <div class="col-md-5">
                    <div class="text-secondary small">Dataset Hash</div>
                    <code>{{ $source->repository_dataset_hash }}</code>
                </div>
                <div class="col-md-2">
                    <div class="text-secondary small">Algorithm</div>
                    <code>{{ $source->matching_algorithm }}</code>
                </div>
                <div class="col-md-2">
                    <div class="text-secondary small">Last Pulled</div>
                    <div>{{ $source->last_pulled_at?->format('d M Y, h:i A') ?: '—' }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body py-3">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Match Status</label>
                    <select class="form-select" name="status">
                        @foreach(['all'=>'All','review'=>'Needs Review','matched'=>'Matched'] as $key=>$label)
                            <option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label">Search</label>
                    <input class="form-control" name="search" value="{{ $search }}" placeholder="Current/previous reg, name, father, mother, cadre">
                </div>
                <div class="col-md-auto">
                    <button class="btn btn-outline-primary">Apply</button>
                    <a class="btn btn-outline-secondary" href="{{ route('choice-optimization.historical.show',$source) }}">Clear</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title">Matched Historical Recommendations</h3>
                <div class="card-subtitle">
                    MATCHED rows have exact primary identity and no supporting conflict. REVIEW rows need operator confirmation in the next phase.
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                <tr>
                    <th>Current Candidate</th>
                    <th>Previous BCS Record</th>
                    <th>Recommended Cadre</th>
                    <th>Method</th>
                    <th>Status</th>
                    <th>Evidence</th>
                </tr>
                </thead>
                <tbody>
                @forelse($matches as $match)
                    @php
                        $support = (array) data_get($match->match_evidence, 'supporting', []);
                    @endphp
                    <tr>
                        <td>
                            <div><strong>Reg:</strong> <code>{{ $match->current_reg }}</code></div>
                        </td>
                        <td>
                            <div><strong>{{ $match->previous_name ?: '—' }}</strong></div>
                            <div class="small">Reg: <code>{{ $match->previous_reg ?: '—' }}</code></div>
                            <div class="small text-secondary">Father: {{ $match->previous_fname ?: '—' }}</div>
                        </td>
                        <td><code>{{ $match->previous_cadre ?: '—' }}</code></td>
                        <td><code>{{ $match->match_method }}</code></td>
                        <td>
                            <span class="badge {{ $match->match_status === 'matched' ? 'bg-green-lt' : 'bg-yellow-lt' }}">
                                {{ strtoupper($match->match_status) }}
                            </span>
                        </td>
                        <td>
                            <details class="small">
                                <summary style="cursor:pointer">View evidence</summary>
                                <div class="mt-2">
                                    <div>Name: <strong>{{ strtoupper((string) data_get($support,'name.status','—')) }}</strong></div>
                                    <div>NID: <strong>{{ strtoupper((string) data_get($support,'nid.status','—')) }}</strong></div>
                                    <div>HSC Roll: <strong>{{ strtoupper((string) data_get($support,'hsc_roll.status','—')) }}</strong></div>
                                    <div>HSC Year: <strong>{{ strtoupper((string) data_get($support,'hsc_year.status','—')) }}</strong></div>
                                    <div>Secondary DOB: <strong>{{ strtoupper((string) data_get($support,'secondary_dob.status','—')) }}</strong></div>
                                </div>
                            </details>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-secondary py-5">No matched/review records found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($matches->hasPages())<div class="card-footer">{{ $matches->links() }}</div>@endif
    </div>
</div>
</div>

<script>
(() => {
    const runningStates = ['pull_queued','pulling'];
    let active = runningStates.includes(@json($source->status));
    const url = @json(route('choice-optimization.historical.status',$source));
    const progress = document.getElementById('hist-progress');
    const fmt = new Intl.NumberFormat();

    const set = (id,value) => {
        const el=document.getElementById(id);
        if(el) el.textContent=value;
    };

    const poll = async () => {
        if(!active) return;

        try {
            const response = await fetch(url,{headers:{'Accept':'application/json'},cache:'no-store'});
            if(!response.ok) return;

            const data = await response.json();
            set('hist-status',String(data.status||'').replaceAll('_',' ').toUpperCase());
            set('hist-candidates',fmt.format(data.candidate_count||0));
            set('hist-matched',fmt.format(data.matched_count||0));
            set('hist-review',fmt.format(data.review_count||0));
            set('hist-no-match',fmt.format(data.no_match_count||0));

            const wasActive=active;
            active=!!data.running;
            if(progress) progress.style.display=active?'':'none';

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
