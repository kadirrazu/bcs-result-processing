@extends('layouts.app')

@section('content')
<div class="page-header d-print-none">
    <div class="container-xl">
        <div class="row align-items-center g-2">
            <div class="col">
                <h2 class="page-title">Previous BCS Dataset</h2>
                <div class="text-secondary mt-1">BCS {{ $dataset->repository->bcs_number }} · Version {{ $dataset->version }} · {{ $dataset->original_name }}</div>
            </div>
            <div class="col-auto ms-auto d-flex gap-2">
                <a class="btn btn-outline-primary" href="{{ route('previous-bcs-repository.datasets.detail', $dataset) }}">View Full Dataset</a>
                <a class="btn btn-outline-secondary" href="{{ route('previous-bcs-repository.index') }}">Back to Repository</a>
            </div>
        </div>
    </div>
</div>

<div class="page-body">
<div class="container-xl">
    <div class="row row-cards mb-3">
        @foreach([
            ['Status',strtoupper(str_replace('_',' ',$dataset->status)),'pbr-status'],
            ['Rows',number_format($dataset->total_rows),'pbr-total'],
            ['Ready for Validation',number_format($dataset->valid_rows),'pbr-valid'],
            ['Source Issues',number_format($dataset->invalid_rows),'pbr-invalid'],
        ] as [$label,$value,$id])
            <div class="col-sm-6 col-lg-3"><div class="card h-100"><div class="card-body py-3">
                <div class="text-secondary small">{{ $label }}</div><div class="h3 mb-0" id="{{ $id }}">{{ $value }}</div>
            </div></div></div>
        @endforeach
    </div>

    <div id="pbr-progress-card" class="card mb-3" @if(!in_array($dataset->status,['queued','processing','validation_queued','validating'],true)) style="display:none" @endif>
        <div class="card-body">
            <div class="d-flex justify-content-between mb-2">
                <div><strong>Queue-based staging</strong> <span class="text-secondary small">· JSON polling</span></div>
                <div id="pbr-progress-text">{{ number_format((float)$dataset->progress_percent,1) }}%</div>
            </div>
            <div class="progress progress-sm"><div id="pbr-progress-bar" class="progress-bar" style="width:{{ min(100,(float)$dataset->progress_percent) }}%"></div></div>
        </div>
    </div>

    @if(in_array($dataset->status, ['failed','validation_failed'], true) && $dataset->failure_message)
        <div class="alert alert-danger"><strong>Processing failed:</strong> {{ $dataset->failure_message }}</div>
    @endif

    <div class="card mb-3">
        <div class="card-body py-3">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div>
                    <div class="fw-semibold">Repository Authority</div>
                    <div class="text-secondary small">
                        @if($dataset->status === 'staged')
                            Staging is complete. Queue full validation before this version can become effective.
                        @elseif($dataset->status === 'validation_failed')
                            Validation found {{ number_format($dataset->invalid_rows) }} blocking row(s). Upload a corrected new version or fix the source and re-upload.
                        @elseif($dataset->status === 'validated')
                            Validation passed. Warning-only rows remain approvable. Dataset hash: <code>{{ $dataset->dataset_hash }}</code>
                        @elseif($dataset->status === 'effective')
                            This is the current effective dataset for BCS {{ $dataset->repository->bcs_number }}.
                        @elseif($dataset->status === 'superseded')
                            This historical version has been superseded by a newer effective version.
                        @else
                            Current status: {{ strtoupper(str_replace('_',' ',$dataset->status)) }}.
                        @endif
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-2 align-items-center">
                    @if(in_array($dataset->status, ['staged','validation_failed','validated'], true))
                        <form method="POST" action="{{ route('previous-bcs-repository.datasets.validate',$dataset) }}" class="mb-0">
                            @csrf
                            <button class="btn btn-outline-primary" type="submit">
                                {{ $dataset->status === 'staged' ? 'Validate Dataset' : 'Re-validate Dataset' }}
                            </button>
                        </form>
                    @elseif(in_array($dataset->status, ['validation_queued','validating'], true))
                        <button class="btn btn-outline-secondary" type="button" disabled>Validation in progress…</button>
                    @endif
                </div>
            </div>

            @if($dataset->status === 'validated' && (int)$dataset->invalid_rows === 0)
                <hr class="my-3">
                <form method="POST" action="{{ route('previous-bcs-repository.datasets.effective',$dataset) }}" class="row g-2 align-items-end">
                    @csrf
                    <div class="col-md">
                        <label class="form-label">Make this version effective</label>
                        <input class="form-control @error('confirmation') is-invalid @enderror" name="confirmation" placeholder="Type EFFECTIVE" autocomplete="off">
                        <div class="form-hint">This will supersede the currently effective version for the same BCS, if any. History is preserved.</div>
                        @error('confirmation')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-auto">
                        <button class="btn btn-success" type="submit">Approve & Make Effective</button>
                    </div>
                </form>
            @endif
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body py-3">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-lg-4">
                    <label class="form-label">Search</label>
                    <input class="form-control" name="search" value="{{ $search }}" placeholder="Reg, name, father, mother, district, SSC/HSC roll, NID, cadre...">
                </div>

                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        @foreach([
                            'all' => 'All',
                            'valid' => 'Valid',
                            'invalid' => 'Invalid',
                            'ready_for_validation' => 'Ready for Validation',
                            'warning' => 'Warning',
                        ] as $key => $label)
                            <option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label">Cadre</label>
                    <select class="form-select" name="cadre">
                        <option value="">All</option>
                        @foreach($cadreOptions as $option)
                            <option value="{{ $option }}" @selected($cadre === (string) $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label">SSC Year</label>
                    <select class="form-select" name="ssc_year">
                        <option value="">All</option>
                        @foreach($sscYearOptions as $option)
                            <option value="{{ $option }}" @selected($sscYear === (string) $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label">HSC Year</label>
                    <select class="form-select" name="hsc_year">
                        <option value="">All</option>
                        @foreach($hscYearOptions as $option)
                            <option value="{{ $option }}" @selected($hscYear === (string) $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-12 d-flex gap-2">
                    <button class="btn btn-outline-primary" type="submit">Apply Filters</button>
                    <a class="btn btn-outline-secondary" href="{{ route('previous-bcs-repository.datasets.show', $dataset) }}">Clear</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title">Staged Rows</h3>
                <div class="card-subtitle">Raw source is preserved; normalized dates are shown for review.</div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                <tr>
                    <th>Row</th><th>Reg / Name</th><th>Primary DOB</th><th>Secondary DOB</th>
                    <th>SSC</th><th>HSC</th><th>Cadre</th><th>Status / Review</th><th class="text-end">Action</th>
                </tr>
                </thead>
                <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td>{{ $row->source_row }}</td>
                        <td>
                            <div><code>{{ $row->reg ?: '—' }}</code></div>
                            <div class="small">{{ $row->name ?: '—' }}</div>
                            @if($row->fname)<div class="small text-secondary">Father: {{ $row->fname }}</div>@endif
                        </td>
                        <td>
                            <div>{{ $row->b_date?->format('d M Y') ?: '—' }}</div>
                            <div class="small text-secondary">Raw: {{ $row->b_date_raw ?: '—' }}</div>
                        </td>
                        <td>
                            <div>{{ $row->dob?->format('d M Y') ?: '—' }}</div>
                            <div class="small text-secondary">Raw: {{ $row->dob_raw ?: '—' }}</div>
                        </td>
                        <td><code>{{ $row->ssc_roll ?: '—' }}</code><div class="small text-secondary">{{ $row->ssc_year ?: '—' }}</div></td>
                        <td><code>{{ $row->hsc_roll ?: '—' }}</code><div class="small text-secondary">{{ $row->hsc_year ?: '—' }}</div></td>
                        <td><code>{{ $row->cadre ?: '—' }}</code></td>
                        <td>
                            @php
                                $rowBadge = match($row->validation_status) {
                                    'valid' => 'bg-green-lt',
                                    'ready_for_validation' => 'bg-blue-lt',
                                    default => 'bg-red-lt',
                                };
                            @endphp
                            <span class="badge {{ $rowBadge }}">
                                {{ strtoupper(str_replace('_',' ',$row->validation_status)) }}
                            </span>
                            @if($row->validation_warnings)
                                <span class="badge bg-yellow-lt ms-1">WARNING</span>
                            @endif
                            @if($row->validation_errors || $row->validation_warnings)
                                <details class="small mt-1">
                                    <summary style="cursor:pointer">Review details</summary>
                                    @foreach((array)$row->validation_errors as $error)
                                        <div class="text-danger">{{ $error['code'] ?? 'ERROR' }}: {{ $error['message'] ?? '' }}</div>
                                    @endforeach
                                    @foreach((array)$row->validation_warnings as $warning)
                                        <div class="text-warning">{{ $warning['code'] ?? 'WARNING' }}: {{ $warning['message'] ?? '' }}</div>
                                    @endforeach
                                </details>
                            @endif
                        </td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-outline-primary"
                               href="{{ route('previous-bcs-repository.datasets.rows.show', [$dataset, $row]) }}">View</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-center text-secondary py-5">Rows are not staged yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($rows->hasPages())<div class="card-footer">{{ $rows->links() }}</div>@endif
    </div>
</div>
</div>

<script>
(() => {
    const runningStates = ['queued','processing','validation_queued','validating'];
    let active = runningStates.includes(@json($dataset->status));
    const url = @json(route('previous-bcs-repository.datasets.status',$dataset));
    const card = document.getElementById('pbr-progress-card');
    const bar = document.getElementById('pbr-progress-bar');
    const text = document.getElementById('pbr-progress-text');
    const fmt = new Intl.NumberFormat();

    const set = (id,value) => { const el=document.getElementById(id); if(el) el.textContent=value; };

    const poll = async () => {
        if(!active) return;
        try {
            const response = await fetch(url,{headers:{'Accept':'application/json'},cache:'no-store'});
            if(!response.ok) return;
            const data = await response.json();
            set('pbr-status',String(data.status||'').replaceAll('_',' ').toUpperCase());
            set('pbr-total',fmt.format(data.total_rows||0));
            set('pbr-valid',fmt.format(data.valid_rows||0));
            set('pbr-invalid',fmt.format(data.invalid_rows||0));
            const pct=Math.max(0,Math.min(100,Number(data.progress_percent||0)));
            if(bar) bar.style.width=pct+'%';
            if(text) text.textContent=pct.toFixed(1)+'%';
            if(card) card.style.display=data.running?'':'none';

            const wasActive=active;
            active=!!data.running;
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
