@extends('layouts.app')

@section('content')
<style>
    .co-detail-code {
        display:inline-flex; align-items:center; justify-content:center;
        min-width:36px; padding:.2rem .42rem; border-radius:.35rem; font-weight:700;
    }
    .co-detail-category { background:rgba(var(--tblr-blue-rgb),.12); color:var(--tblr-blue); }
    .co-detail-track { background:rgba(var(--tblr-orange-rgb),.14); color:var(--tblr-orange); }
    .co-detail-choice {
        width:56px; min-width:56px; min-height:54px; display:inline-flex; flex-direction:column;
        align-items:center; justify-content:center; gap:.12rem;
        border:1px solid var(--tblr-border-color); border-radius:.45rem;
        padding:.28rem .34rem; margin:0 .22rem .22rem 0; vertical-align:top;
    }
    .co-detail-choice-pos { font-size:.68rem; color:var(--tblr-secondary-color); line-height:1; }
    .co-detail-choice-code { font-weight:700; line-height:1.15; }
    .co-detail-choice.co-same { border-color:rgba(var(--tblr-success-rgb),.40); background:rgba(var(--tblr-success-rgb),.06); }
    .co-detail-choice.co-different { border-color:rgba(var(--tblr-warning-rgb),.50); background:rgba(var(--tblr-warning-rgb),.08); }
    .co-detail-choice.co-effective { border-color:rgba(var(--tblr-primary-rgb),.45); background:rgba(var(--tblr-primary-rgb),.07); }

    .co-detail-choice-lane {
        display:flex; flex-wrap:nowrap; gap:.22rem; overflow-x:auto;
        padding-bottom:.25rem; scrollbar-width:thin; min-width:0;
    }
    .co-detail-stage-meta { min-width:180px; }
</style>
@php
    $rawChoices = collect((array)$row->raw_choices)->filter(fn($v)=>filled($v))->values()->all();
    $cleanOmr = collect((array)$row->validated_omr_choice_codes)->filter(fn($v)=>filled($v))->values()->all();
    $details = collect((array)$row->choice_validation_details);
    $kept = $details->where('result','kept')->count();
    $removed = $details->where('result','removed')->count();
    $expanded = $details->where('result','expanded')->count();

    $category = $candidate?->cadre_category;
    $categoryCode = is_object($category) && method_exists($category,'code') ? $category->code() : (string)($category ?? '—');

    $effectiveCodes = $effectiveChoice
        ? array_values((array)$effectiveChoice->effective_choice_codes)
        : ($row->effective_change_choice === 'YES' ? $cleanOmr : $validatedChoices);

    $statusBadge = match($row->validation_status){
        'valid'=>'bg-green-lt',
        'conflict','decision_review'=>'bg-yellow-lt',
        'pending'=>'bg-secondary-lt',
        default=>'bg-red-lt',
    };
@endphp

<div class="page-header d-print-none">
    <div class="container-xl">
        <div class="row align-items-center g-2">
            <div class="col">
                <h2 class="page-title">OMR Choice Details</h2>
                <div class="text-secondary mt-1">Batch #{{ $batch->id }} · Source Row {{ $row->source_row }}</div>
            </div>
            <div class="col-auto ms-auto">
                <a href="{{ route('choice-optimization.omr.show',$batch) }}" class="btn btn-outline-secondary">Back to OMR Review</a>
            </div>
        </div>
    </div>
</div>

<div class="page-body">
<div class="container-xl">

    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="text-secondary small">Candidate</div>
                    <div class="fw-semibold">{{ $candidate?->name ?: '—' }}</div>
                    <div class="small">Reg: <code>{{ $row->effective_reg ?: $row->raw_reg ?: '—' }}</code></div>
                    @if($row->raw_reg && $row->effective_reg && $row->raw_reg !== $row->effective_reg)
                        <div class="small text-secondary">Raw OMR Reg: <code>{{ $row->raw_reg }}</code></div>
                    @endif
                </div>
                <div class="col-md-2">
                    <div class="text-secondary small">Original Category</div>
                    <div><span class="co-detail-code co-detail-category">{{ $categoryCode ?: '—' }}</span></div>
                </div>
                <div class="col-md-2">
                    <div class="text-secondary small">Written Track</div>
                    <div><span class="co-detail-code co-detail-track">{{ $row->written_qualified_track ?: '—' }}</span></div>
                </div>
                <div class="col-md-2">
                    <div class="text-secondary small">OMR / Effective</div>
                    <div><strong>{{ $row->change_choice ?: '—' }}</strong> → <strong>{{ $row->effective_change_choice ?: '—' }}</strong></div>
                </div>
                <div class="col-md-2">
                    <div class="text-secondary small">Validation</div>
                    <div><span class="badge {{ $statusBadge }}">{{ strtoupper(str_replace('_',' ',$row->validation_status)) }}</span></div>
                    @if($row->choice_validation_status && strtolower((string)$row->choice_validation_status) !== strtolower((string)$row->validation_status))
                        <div class="small text-secondary mt-1">Choice: {{ strtoupper(str_replace('_',' ',$row->choice_validation_status)) }}</div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">
            <div>
                <h3 class="card-title">Choice Lineage</h3>
                <div class="card-subtitle">Every source and derived choice remains traceable; no raw source is overwritten.</div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead><tr><th style="width:220px">Stage</th><th>Choice Sequence</th></tr></thead>
                <tbody>
                    @foreach([
                        ['registration','Registration Choice',$registrationChoices,'Original application/registration source'],
                        ['validated','Finalized Validated Choice',$validatedChoices,'Finalized Choice Validation output'],
                        ['omr','Raw OMR Choice',$rawChoices,'Choice written in Viva OMR'],
                        ['clean_omr','Expanded / Validated OMR Choice',$cleanOmr,'OMR after full Choice Validation rules and expansion'],
                        ['effective','Current Effective Choice',$effectiveCodes,$effectiveChoice ? 'Approved consolidated choice' : 'Current derived preview before approval'],
                    ] as [$stageKey,$stage,$codes,$meaning])
                        <tr>
                            <td class="co-detail-stage-meta">
                                <div class="fw-semibold">{{ $stage }}</div>
                                <div class="text-secondary small mt-1">{{ $meaning }}</div>
                            </td>
                            <td>
                                <div class="co-detail-choice-lane">
                                    @forelse($codes as $i=>$code)
                                        @php
                                            $reference = match($stageKey) {
                                                'validated' => $registrationChoices[$i] ?? null,
                                                'omr', 'clean_omr' => $validatedChoices[$i] ?? null,
                                                'effective' => ($cleanOmr[$i] ?? ($validatedChoices[$i] ?? null)),
                                                default => null,
                                            };
                                            $class = $stageKey === 'registration'
                                                ? ''
                                                : (($reference === $code) ? 'co-same' : 'co-different');
                                            if($stageKey === 'effective') $class = 'co-effective';
                                        @endphp
                                        <span class="co-detail-choice {{ $class }}">
                                            <span class="co-detail-choice-pos">#{{ str_pad((string)($i+1),2,'0',STR_PAD_LEFT) }}</span>
                                            <code class="co-detail-choice-code">{{ $code }}</code>
                                        </span>
                                    @empty
                                        <span class="text-secondary">—</span>
                                    @endforelse
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="row row-cards mb-3">
        <div class="col-sm-4"><div class="card h-100"><div class="card-body"><div class="text-secondary">Kept</div><div class="h2 mb-0">{{ $kept }}</div></div></div></div>
        <div class="col-sm-4"><div class="card h-100"><div class="card-body"><div class="text-secondary">Removed</div><div class="h2 mb-0">{{ $removed }}</div></div></div></div>
        <div class="col-sm-4"><div class="card h-100"><div class="card-body"><div class="text-secondary">Expanded</div><div class="h2 mb-0">{{ $expanded }}</div></div></div></div>
    </div>

    @if(!empty($row->validation_errors) || !empty($row->validation_warnings))
        <div class="card mb-3">
            <div class="card-header"><h3 class="card-title">Validation Messages</h3></div>
            <div class="card-body">
                @foreach((array)$row->validation_errors as $message)
                    <div class="alert alert-danger py-2 mb-2">
                        <strong>{{ $message['code'] ?? 'ERROR' }}</strong>
                        @if(!empty($message['message'])) — {{ $message['message'] }} @endif
                    </div>
                @endforeach
                @foreach((array)$row->validation_warnings as $message)
                    <div class="alert alert-warning py-2 mb-2">
                        <strong>{{ $message['code'] ?? 'WARNING' }}</strong>
                        @if(!empty($message['message'])) — {{ $message['message'] }} @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="card mb-3">
        <div class="card-header">
            <div>
                <h3 class="card-title">Expansion / Removal Details</h3>
                <div class="card-subtitle">Human-readable trace of what happened to each OMR choice and why.</div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th>Source</th>
                        <th>Resolved As</th>
                        <th>Result</th>
                        <th>Output</th>
                        <th>Reason</th>
                        <th>Eligibility Evidence</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($details as $detail)
                    @php
                        $result = strtolower((string)($detail['result'] ?? ''));
                        $resultBadge = match($result){
                            'kept'=>'bg-green-lt','expanded'=>'bg-blue-lt','removed'=>'bg-red-lt',default=>'bg-secondary-lt'
                        };
                        $snapshot = (array)($detail['eligibility_snapshot'] ?? []);
                    @endphp
                    <tr>
                        <td>
                            <div class="fw-semibold">#{{ str_pad((string)($detail['source_position'] ?? 0),2,'0',STR_PAD_LEFT) }} · {{ $detail['source_code'] ?? '—' }}</div>
                            <div class="small text-secondary">{{ $detail['source_column'] ?? '' }}</div>
                        </td>
                        <td>{{ strtoupper((string)($detail['resolved_type'] ?? '—')) }}</td>
                        <td><span class="badge {{ $resultBadge }}">{{ strtoupper($result ?: '—') }}</span></td>
                        <td>
                            @if(!empty($detail['output_code']))
                                #{{ str_pad((string)($detail['output_position'] ?? 0),2,'0',STR_PAD_LEFT) }} · <code>{{ $detail['output_code'] }}</code>
                            @else
                                —
                            @endif
                            @if(!empty($detail['expanded_from_code']))
                                <div class="small text-secondary">Expanded from {{ $detail['expanded_from_code'] }}</div>
                            @endif
                        </td>
                        <td>
                            @if(!empty($detail['reason_code']))<div class="fw-semibold">{{ $detail['reason_code'] }}</div>@endif
                            <div class="small text-secondary">{{ $detail['reason_message'] ?? 'Choice retained/expanded under current rules.' }}</div>
                        </td>
                        <td class="small">
                            @if($snapshot !== [])
                                <details>
                                    <summary class="text-primary" style="cursor:pointer">View evidence</summary>
                                    <div class="mt-2">
                                        <div>Candidate Bachelor: <code>{{ $snapshot['candidate_bachelor_subject_code'] ?? '—' }}</code></div>
                                        <div>Allowed Bachelor: <code>{{ implode(', ',(array)($snapshot['allowed_bachelor_subject_codes'] ?? [])) ?: '—' }}</code></div>
                                        <div>Candidate PRS: <code>{{ $snapshot['candidate_prs_code'] ?? '—' }}</code></div>
                                        <div>Allowed PRS: <code>{{ implode(', ',(array)($snapshot['allowed_prs_codes'] ?? [])) ?: '—' }}</code></div>
                                        <div>Circular Type: <code>{{ $snapshot['circular_type'] ?? '—' }}</code></div>
                                        <div>Circular Entry: <code>{{ $snapshot['circular_entry_id'] ?? '—' }}</code></div>
                                    </div>
                                </details>
                            @else
                                <span class="text-secondary">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-secondary py-4">No OMR choice transformation detail recorded.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header"><h3 class="card-title">Operator / Resolution Audit</h3></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="fw-semibold mb-1">Registration Resolution</div>
                    @if($row->resolution_status === 'resolved')
                        <div>{{ $row->resolution_reason ?: 'Resolved without note.' }}</div>
                        <div class="small text-secondary mt-1">
                            By {{ $actors->get((int)$row->resolved_by)?->name ?: ('User #'.($row->resolved_by ?: '—')) }}
                            @if($row->resolved_at) · {{ $row->resolved_at->format('d M Y, h:i A') }} @endif
                        </div>
                    @else
                        <div class="text-secondary">No registration correction recorded.</div>
                    @endif
                </div>
                <div class="col-md-6">
                    <div class="fw-semibold mb-1">OMR Decision Resolution</div>
                    @if($row->decision_resolution)
                        <div><code>{{ $row->decision_resolution }}</code></div>
                        <div>{{ $row->decision_resolution_reason }}</div>
                        <div class="small text-secondary mt-1">
                            By {{ $actors->get((int)$row->decision_resolved_by)?->name ?: ('User #'.($row->decision_resolved_by ?: '—')) }}
                            @if($row->decision_resolved_at) · {{ $row->decision_resolved_at->format('d M Y, h:i A') }} @endif
                        </div>
                    @else
                        <div class="text-secondary">No operator decision override recorded.</div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @if($effectiveChoice)
        <div class="card">
            <div class="card-header"><h3 class="card-title">Approved Effective Choice</h3></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3"><div class="text-secondary small">Source</div><strong>{{ strtoupper(str_replace('_',' ',$effectiveChoice->choice_source)) }}</strong></div>
                    <div class="col-md-3"><div class="text-secondary small">Reason Code</div><code>{{ $effectiveChoice->change_reason_code }}</code></div>
                    <div class="col-md-6"><div class="text-secondary small">Reason</div>{{ $effectiveChoice->change_reason_text }}</div>
                </div>
            </div>
        </div>
    @endif

</div>
</div>
@endsection
