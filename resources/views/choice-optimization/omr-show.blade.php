@extends('layouts.app')

@section('content')
<style>
    .co-review-card { transition: opacity .24s ease, transform .24s ease, box-shadow .24s ease; }
    .co-review-card.co-resolving { opacity: .45; }
    .co-review-card.co-resolved { opacity: 0; transform: translateY(-8px); }
    .co-review-card.co-current-review { box-shadow: 0 0 0 2px rgba(var(--tblr-warning-rgb), .18); }

    .co-summary-grid {
        display: grid;
        grid-template-columns: repeat(6, minmax(110px, 1fr));
        border: 1px solid var(--tblr-border-color);
        border-radius: .5rem;
        overflow: hidden;
    }
    .co-summary-cell {
        min-width: 0;
        border-right: 1px solid var(--tblr-border-color);
        display: grid;
        grid-template-rows: 42px minmax(64px, 1fr);
    }
    .co-summary-cell:last-child { border-right: 0; }
    .co-summary-label {
        display:flex; align-items:center; min-height:42px; padding:.55rem .85rem;
        border-bottom:1px solid var(--tblr-border-color);
        color:var(--tblr-secondary-color); font-size:.72rem; font-weight:600;
        text-transform:uppercase; letter-spacing:.02em;
    }
    .co-summary-value {
        min-height:64px; padding:.7rem .85rem;
        display:flex; flex-direction:column; justify-content:center;
    }

    .co-choice-line {
        display:grid;
        grid-template-columns: 190px minmax(0, 1fr);
        gap:.75rem;
        align-items:start;
        padding:.62rem 0;
        border-bottom:1px dashed var(--tblr-border-color);
    }
    .co-choice-line:last-child { border-bottom:0; }
    .co-choice-title { font-weight:600; }
    .co-choice-note { color:var(--tblr-secondary-color); font-size:.74rem; }
    .co-choice-values { display:flex; flex-wrap:nowrap; gap:.35rem; overflow-x:auto; padding-bottom:.2rem; scrollbar-width:thin; }
    .co-choice-pill {
        display:inline-flex; align-items:center; gap:.35rem;
        border:1px solid var(--tblr-border-color); border-radius:.4rem;
        padding:.25rem .45rem; background:var(--tblr-bg-surface); font-size:.82rem;
    }
    .co-choice-pos { color:var(--tblr-secondary-color); font-size:.70rem; }


    .co-candidate-line {
        display:flex; flex-wrap:wrap; align-items:center; gap:.35rem;
        font-weight:600;
    }
    .co-reg-number {
        display:inline-block; padding:.12rem .38rem; border-radius:.3rem;
        background:rgba(var(--tblr-primary-rgb),.10); color:var(--tblr-primary);
        font-family:var(--tblr-font-monospace);
    }
    .co-code-badge {
        display:inline-flex; align-items:center; justify-content:center;
        min-width:34px; padding:.18rem .38rem; border-radius:.35rem; font-weight:700;
    }
    .co-code-category { background:rgba(var(--tblr-blue-rgb),.12); color:var(--tblr-blue); }
    .co-code-track { background:rgba(var(--tblr-azure-rgb),.12); color:var(--tblr-azure); }

    .co-choice-pill {
        width:54px; min-width:54px; min-height:52px;
        display:flex; flex-direction:column; align-items:center; justify-content:center;
        gap:.10rem; border:1px solid var(--tblr-border-color); border-radius:.45rem;
        padding:.28rem .34rem; background:var(--tblr-bg-surface); font-size:.82rem;
    }
    .co-choice-pos {
        display:block; width:100%; text-align:center;
        color:var(--tblr-secondary-color); font-size:.68rem; line-height:1.05;
    }
    .co-choice-code {
        display:block; width:100%; text-align:center; font-weight:700; line-height:1.2;
    }
    .co-choice-scroll-hint { color:var(--tblr-secondary-color); font-size:.70rem; margin-top:.20rem; }
    .co-choice-pill.co-same { border-color:rgba(var(--tblr-success-rgb),.40); background:rgba(var(--tblr-success-rgb),.06); }
    .co-choice-pill.co-different { border-color:rgba(var(--tblr-warning-rgb),.50); background:rgba(var(--tblr-warning-rgb),.08); }
    .co-choice-pill.co-invalid { border-color:rgba(var(--tblr-danger-rgb),.45); background:rgba(var(--tblr-danger-rgb),.07); }

    .co-decision-option {
        position:relative; height:100%; border:1px solid var(--tblr-border-color);
        border-radius:.5rem; padding:.85rem .85rem .85rem 2.55rem; cursor:pointer;
    }
    .co-decision-option input { position:absolute; left:.9rem; top:1.05rem; }
    .co-decision-yes { border-color:rgba(var(--tblr-success-rgb),.38); }
    .co-decision-no { border-color:rgba(var(--tblr-danger-rgb),.34); }
    .co-decision-option:has(input:checked) { box-shadow:0 0 0 2px rgba(var(--tblr-primary-rgb),.10); }

    @media(max-width:1199.98px){
        .co-summary-grid{grid-template-columns:repeat(3,1fr)}
        .co-summary-cell:nth-child(3n){border-right:0}
        .co-summary-cell:nth-child(-n+3){border-bottom:1px solid var(--tblr-border-color)}
    }
    @media(max-width:767.98px){
        .co-summary-grid{grid-template-columns:1fr 1fr}
        .co-summary-cell{border-bottom:1px solid var(--tblr-border-color)}
        .co-summary-cell:nth-child(odd){border-right:1px solid var(--tblr-border-color)}
        .co-summary-cell:nth-child(even){border-right:0}
        .co-choice-line{grid-template-columns:1fr}
    }
</style>

<div class="page-header d-print-none">
    <div class="container-xl">
        <div class="row align-items-center g-2">
            <div class="col">
                <h2 class="page-title">OMR Choice Review</h2>
                <div class="text-secondary mt-1">Batch #{{ $batch->id }} · {{ $batch->original_name }}</div>
            </div>
            <div class="col-auto ms-auto d-flex gap-2">
                <a href="{{ request()->fullUrl() }}" class="btn btn-outline-primary">Refresh</a>
                <a href="{{ route('choice-optimization.index') }}" class="btn btn-outline-secondary">Back</a>
            </div>
        </div>
    </div>
</div>

<div class="page-body">
<div class="container-xl">

    <div class="row row-cards mb-3">
        @php $attention = (int)$batch->invalid_rows + (int)$batch->conflict_rows + (int)($batch->review_rows ?? 0); @endphp
        @foreach([
            ['Status', strtoupper(str_replace('_',' ', $batch->status)), 'co-status'],
            ['Total', number_format($batch->total_rows), 'co-total'],
            ['Ready', number_format($batch->valid_rows), 'co-valid'],
            ['Needs Attention', number_format($attention), 'co-attention'],
        ] as [$label,$value,$id])
            <div class="col-sm-6 col-lg-3">
                <div class="card h-100">
                    <div class="card-body py-3">
                        <div class="text-secondary small">{{ $label }}</div>
                        <div class="h3 mb-0" id="{{ $id }}">{{ $value }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card mb-3" id="co-progress-card" @if(!in_array($batch->status, ['queued','processing','validation_queued','validating','approval_queued','approving'], true)) style="display:none" @endif>
        <div class="card-body py-3">
            <div class="d-flex justify-content-between mb-2">
                <div><strong>Background processing</strong> <span class="text-secondary small">· JSON polling</span></div>
                <div id="co-progress-text">{{ number_format((float)$batch->progress_percent,1) }}%</div>
            </div>
            <div class="progress progress-sm">
                <div id="co-progress-bar" class="progress-bar" style="width:{{ min(100,(float)$batch->progress_percent) }}%"></div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body py-3">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div>
                    <div class="fw-semibold">Processing Action</div>
                    <div class="text-secondary small">
                        @if($remainingOperatorReviews > 0)
                            Resolve <strong id="co-review-remaining">{{ number_format($remainingOperatorReviews) }}</strong> operator review(s), then re-validate.
                        @elseif($batch->status === 'validated')
                            Validation is complete. Review details if required, then approve/consolidate.
                        @else
                            Current batch status: {{ strtoupper(str_replace('_',' ', $batch->status)) }}.
                        @endif
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    @if(in_array((string)$batch->status, ['validated','needs_review','validation_failed'], true))
                        <form method="POST" action="{{ route('choice-optimization.omr.revalidate',$batch) }}" class="mb-0">
                            @csrf
                            <button class="btn btn-outline-primary" type="submit">Re-validate OMR Choices</button>
                        </form>
                    @elseif(in_array((string)$batch->status, ['validation_queued','validating'], true))
                        <button class="btn btn-outline-secondary" disabled>Re-validation in progress…</button>
                    @endif

                    @if($batch->status === 'validated' && $remainingOperatorReviews === 0)
                        <form method="POST" action="{{ route('choice-optimization.omr.approve',$batch) }}" class="mb-0">
                            @csrf
                            <button class="btn btn-success" type="submit">Approve & Consolidate</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div id="co-review-complete" class="alert alert-success" @if($remainingOperatorReviews > 0) style="display:none" @endif>
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div><strong>Operator review complete.</strong> Queue OMR Re-validation before approval.</div>
            @if(in_array((string)$batch->status, ['needs_review','validation_failed','validated'], true))
                <form method="POST" action="{{ route('choice-optimization.omr.revalidate',$batch) }}" class="mb-0">
                    @csrf
                    <button class="btn btn-success" type="submit">Queue OMR Re-validation</button>
                </form>
            @endif
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body py-3">
            <form method="GET" class="row g-2 align-items-center">
                <div class="col-md-3">
                    <select class="form-select" name="status">
                        @foreach([
                            'all'=>'All Status','decision_review'=>'Decision Review','conflict'=>'Conflict',
                            'invalid'=>'Invalid','warning'=>'Warning','operator_confirmed'=>'Operator Confirmed',
                            'valid'=>'Valid','pending'=>'Pending'
                        ] as $key=>$label)
                            <option value="{{ $key }}" @selected($status===$key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4"><input class="form-control" name="search" value="{{ $search }}" placeholder="Registration"></div>
                <div class="col-md-auto"><button class="btn btn-outline-primary">Filter</button></div>
                <div class="col-md ms-md-auto text-md-end text-secondary small">
                    Detailed expansion/removal evidence is available from each candidate's <strong>Details</strong> page.
                </div>
            </form>
        </div>
    </div>

    <div id="co-review-list" class="d-flex flex-column gap-3">
        @forelse($rows as $row)
            @php
                $rawChoices = collect((array)$row->raw_choices)->filter(fn($v)=>filled($v))->values()->all();
                $validatedChoices = $row->registration_id ? ($validatedChoiceMap[(int)$row->registration_id] ?? []) : [];
                $registrationChoices = $row->registration_id ? ($registrationChoiceMap[(int)$row->registration_id] ?? []) : [];
                $candidateContext = $row->registration_id ? ($candidateContextMap[(int)$row->registration_id] ?? []) : [];
                $cleanOmrChoices = collect((array)$row->validated_omr_choice_codes)->filter(fn($v)=>filled($v))->values()->all();

                $identityErrorCodes = ['INVALID_OMR_REGISTRATION','WRITTEN_REGISTRATION_AMBIGUOUS','DUPLICATE_OMR_REGISTRATION','OMR_REGISTRATION_REQUIRED'];
                $needsRegistrationResolution = in_array($row->validation_status,['conflict','invalid'],true)
                    && collect((array)$row->validation_errors)->contains(fn($e)=>in_array($e['code']??'',$identityErrorCodes,true));
                $isReviewItem = $row->validation_status === 'decision_review' || $needsRegistrationResolution;

                $badge = match($row->validation_status) {
                    'valid'=>'bg-green-lt',
                    'conflict','decision_review'=>'bg-yellow-lt',
                    'pending'=>'bg-secondary-lt',
                    default=>'bg-red-lt',
                };
                $firstError = collect((array)$row->validation_errors)->first();
                $firstWarning = collect((array)$row->validation_warnings)->first();
            @endphp

            <div class="card co-review-card {{ $isReviewItem ? 'co-review-item' : '' }}"
                 data-review-item="{{ $isReviewItem ? '1' : '0' }}" data-row-id="{{ $row->id }}">
                <div class="card-header py-3">
                    <div class="d-flex flex-wrap align-items-center gap-2 w-100">
                        <div class="co-candidate-line">
                            <span>Reg:</span>
                            <span class="co-reg-number">{{ $row->effective_reg ?: $row->raw_reg ?: '—' }}</span>
                            <span class="text-secondary">[{{ $candidateContext['name'] ?? 'Candidate' }}]</span>
                        </div>
                        <span class="text-secondary">Category: <span class="co-code-badge co-code-category">{{ $candidateContext['category_code'] ?? '—' }}</span></span>
                        <span class="text-secondary">Written Track: <span class="co-code-badge co-code-track">{{ $row->written_qualified_track ?: '—' }}</span></span>
                        <span class="badge {{ $row->change_choice === 'YES' ? 'bg-green-lt' : 'bg-red-lt' }}">OMR {{ $row->change_choice ?: '—' }}</span>
                        <span class="badge {{ $badge }}">{{ strtoupper(str_replace('_',' ',$row->validation_status)) }}</span>
                        <a href="{{ route('choice-optimization.omr.row.show',[$batch,$row]) }}" class="btn btn-sm btn-outline-primary ms-auto">Details</a>
                    </div>
                </div>

                <div class="card-body">
                    @if($isReviewItem || $row->effective_change_choice === 'YES')
                        <div class="mb-3">
                            @foreach([
                                ['registration','Registration Choice','Original source',$registrationChoices],
                                ['validated','Finalized Validated Choice','Choice Validation output',$validatedChoices],
                                ['omr','OMR Options','Viva OMR source',$rawChoices],
                            ] as [$sourceKey,$title,$note,$choices])
                                <div class="co-choice-line">
                                    <div>
                                        <div class="co-choice-title">{{ $title }}</div>
                                        <div class="co-choice-note">{{ $note }}</div>
                                    </div>
                                    <div class="co-choice-values">
                                        @forelse($choices as $i=>$code)
                                            @php
                                                $referenceCode = $sourceKey === 'registration'
                                                    ? ($registrationChoices[$i] ?? null)
                                                    : ($validatedChoices[$i] ?? null);
                                                $differenceClass = $sourceKey === 'registration'
                                                    ? ''
                                                    : (($referenceCode === $code) ? 'co-same' : 'co-different');
                                            @endphp
                                            <span class="co-choice-pill {{ $differenceClass }}">
                                                <span class="co-choice-pos">#{{ str_pad((string)($i+1),2,'0',STR_PAD_LEFT) }}</span>
                                                <code class="co-choice-code">{{ $code }}</code>
                                            </span>
                                        @empty
                                            <span class="text-secondary small">—</span>
                                        @endforelse
                                    </div>
                                    @if(count($choices) > 10)
                                        <div class="co-choice-scroll-hint">#01 → #{{ str_pad((string) count($choices), 2, '0', STR_PAD_LEFT) }} · scroll horizontally if needed</div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if($row->effective_change_choice === 'YES' && $row->choice_validation_status === 'valid' && $cleanOmrChoices !== [])
                        <div class="alert alert-success py-2 mb-3">
                            <strong>Validated OMR Choice:</strong>
                            <code class="ms-1">{{ implode(' ', $cleanOmrChoices) }}</code>
                            <a href="{{ route('choice-optimization.omr.row.show',[$batch,$row]) }}" class="ms-2">View validation trace</a>
                        </div>
                    @elseif($firstError || $firstWarning)
                        <div class="alert {{ $firstError ? 'alert-danger' : 'alert-warning' }} py-2 mb-3">
                            <strong>{{ ($firstError['code'] ?? $firstWarning['code'] ?? 'REVIEW') }}:</strong>
                            {{ ($firstError['message'] ?? $firstWarning['message'] ?? '') }}
                            @if(count((array)$row->validation_errors) + count((array)$row->validation_warnings) > 1)
                                <a href="{{ route('choice-optimization.omr.row.show',[$batch,$row]) }}" class="ms-2">View all details</a>
                            @endif
                        </div>
                    @endif

                    @if($row->validation_status === 'decision_review')
                        <div class="border rounded p-3">
                            <div class="fw-semibold mb-2">Operator Decision</div>
                            <form method="POST" action="{{ route('choice-optimization.omr.resolve-decision',$row) }}" class="co-review-form">
                                @csrf
                                <div class="row g-2 mb-3">
                                    <div class="col-lg-6">
                                        <label class="co-decision-option co-decision-yes d-block">
                                            <input class="form-check-input" type="radio" name="resolution" value="consider_no_as_yes_keep_options" required>
                                            <div class="fw-semibold">Consider NO as YES and keep the OMR options</div>
                                            <div class="text-secondary small">OMR options will be re-validated before becoming effective.</div>
                                        </label>
                                    </div>
                                    <div class="col-lg-6">
                                        <label class="co-decision-option co-decision-no d-block">
                                            <input class="form-check-input" type="radio" name="resolution" value="keep_no_discard_options" required>
                                            <div class="fw-semibold">Consider NO as NO and discard the OMR options</div>
                                            <div class="text-secondary small">Finalized Validated Choice remains effective.</div>
                                        </label>
                                    </div>
                                </div>
                                <div class="row g-2 align-items-end">
                                    <div class="col-md">
                                        <label class="form-label">Administrative reason <span class="text-danger">*</span></label>
                                        <textarea class="form-control" name="reason" rows="2" maxlength="500" required></textarea>
                                    </div>
                                    <div class="col-md-auto">
                                        <button class="btn btn-primary" type="submit">Confirm & Continue</button>
                                    </div>
                                </div>
                                <span class="small text-secondary co-save-state"></span>
                            </form>
                        </div>
                    @elseif($needsRegistrationResolution)
                        <div class="border rounded p-3">
                            <div class="fw-semibold mb-2">Registration Conflict Resolution</div>
                            <form method="POST" action="{{ route('choice-optimization.omr.resolve-registration',$row) }}" class="row g-2 co-review-form">
                                @csrf
                                <div class="col-md-4">
                                    <label class="form-label">Correct Registration</label>
                                    <input class="form-control" name="effective_reg" value="{{ $row->effective_reg }}" required>
                                </div>
                                <div class="col-md">
                                    <label class="form-label">Administrative reason</label>
                                    <input class="form-control" name="reason" required>
                                </div>
                                <div class="col-md-auto d-flex align-items-end">
                                    <button class="btn btn-primary" type="submit">Save & Continue</button>
                                </div>
                                <div class="col-12"><span class="small text-secondary co-save-state"></span></div>
                            </form>
                        </div>
                    @elseif(!$isReviewItem)
                        <div class="d-flex flex-wrap align-items-center gap-2 text-secondary small">
                            <span>Effective decision: <strong>{{ $row->effective_change_choice ?: '—' }}</strong></span>
                            <span>·</span>
                            <span>Choice validation: <strong>{{ strtoupper(str_replace('_',' ',$row->choice_validation_status ?: '—')) }}</strong></span>
                            @if($row->decision_resolution)
                                <span>· Operator decision recorded</span>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        @empty
            <div class="card"><div class="card-body text-center text-secondary py-5">No OMR rows found.</div></div>
        @endforelse
    </div>

    @if($rows->hasPages()) <div class="mt-3">{{ $rows->links() }}</div> @endif
</div>
</div>

<script>
(() => {
    const runningStates = ['queued','processing','validation_queued','validating','approval_queued','approving'];
    let active = runningStates.includes(@json($batch->status));
    const url = @json(route('choice-optimization.omr.status',$batch));
    const card = document.getElementById('co-progress-card');
    const bar = document.getElementById('co-progress-bar');
    const text = document.getElementById('co-progress-text');
    const fmt = new Intl.NumberFormat();

    const set = (id,value) => { const el=document.getElementById(id); if(el) el.textContent=value; };

    const poll = async () => {
        if(!active) return;
        try{
            const response = await fetch(url, {headers:{'Accept':'application/json'},cache:'no-store'});
            if(!response.ok) return;
            const data = await response.json();
            set('co-status', String(data.status||'').replaceAll('_',' ').toUpperCase());
            set('co-total', fmt.format(data.total_rows||0));
            set('co-valid', fmt.format(data.valid_rows||0));
            set('co-attention', fmt.format((data.invalid_rows||0)+(data.conflict_rows||0)+(data.review_rows||0)));
            const pct=Math.max(0,Math.min(100,Number(data.progress_percent||0)));
            if(bar) bar.style.width=pct+'%';
            if(text) text.textContent=pct.toFixed(1)+'%';
            if(card) card.style.display=data.running?'':'none';

            const wasActive = active;
            active = !!data.running;

            // The progress counters are updated by JSON polling. Processing Action,
            // filters and row-level validation content are server-rendered, so refresh
            // exactly once when a background run transitions from running to finished.
            if (wasActive && !active) {
                window.setTimeout(() => {
                    window.location.replace(window.location.href);
                }, 250);
            }
        }catch(_){}
    };
    poll();
    window.setInterval(poll,1500);

    const reviewItems = () => [...document.querySelectorAll('.co-review-item:not(.co-resolved)')];
    const focusNextReview = () => {
        reviewItems().forEach(el=>el.classList.remove('co-current-review'));
        const next=reviewItems()[0];
        if(next){ next.classList.add('co-current-review'); next.scrollIntoView({behavior:'smooth',block:'center'}); }
    };

    const updateRemaining = (remaining) => {
        const el=document.getElementById('co-review-remaining');
        if(el) el.textContent=fmt.format(remaining);
        const complete=document.getElementById('co-review-complete');
        if(complete && remaining===0) complete.style.display='';
    };

    document.querySelectorAll('.co-review-form').forEach(form=>{
        form.addEventListener('submit',async(event)=>{
            event.preventDefault();
            if(!form.reportValidity()) return;
            const card=form.closest('.co-review-card');
            const state=form.querySelector('.co-save-state');
            card?.classList.add('co-resolving');
            if(state) state.textContent='Saving…';
            try{
                const response=await fetch(form.action,{
                    method:'POST',
                    body:new FormData(form),
                    headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'},
                });
                const data=await response.json().catch(()=>({}));
                if(!response.ok) throw new Error(data.message||'Unable to save decision.');
                if(state) state.textContent='Saved';
                card?.classList.add('co-resolved');
                window.setTimeout(()=>{
                    if(card) card.style.display='none';
                    updateRemaining(Number(data.remaining_review_rows||0));
                    focusNextReview();
                },260);
            }catch(error){
                card?.classList.remove('co-resolving');
                if(state){ state.textContent=error.message; state.classList.add('text-danger'); }
            }
        });
    });

    focusNextReview();
})();
</script>
@endsection
