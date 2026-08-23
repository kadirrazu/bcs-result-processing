@extends('layouts.app')

@section('content')
<style>
    .co-review-card {
        transition: opacity .24s ease, transform .24s ease, box-shadow .24s ease;
    }
    .co-review-card.co-resolving { opacity: .45; }
    .co-review-card.co-resolved { opacity: 0; transform: translateY(-10px); }
    .co-review-card.co-current-review { box-shadow: 0 0 0 2px rgba(var(--tblr-warning-rgb), .20), 0 .35rem 1rem rgba(0,0,0,.06); }

    .co-context-grid {
        display: grid;
        grid-template-columns: repeat(6, minmax(120px, 1fr));
        gap: 0;
    }
    .co-context-cell {
        min-width: 0;
        padding: 0;
        border-right: 1px solid var(--tblr-border-color);
        display: grid;
        grid-template-rows: 44px minmax(72px, 1fr);
        align-self: stretch;
    }
    .co-context-cell:last-child { border-right: 0; }
    .co-context-label {
        display: flex;
        align-items: center;
        min-height: 44px;
        padding: .65rem 1rem;
        border-bottom: 1px solid var(--tblr-border-color);
        color: var(--tblr-secondary-color);
        font-size: .74rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .02em;
    }
    .co-context-body {
        min-height: 72px;
        padding: .8rem 1rem;
        display: flex;
        flex-direction: column;
        justify-content: center;
        gap: .25rem;
    }
    .co-context-value { font-weight: 600; }

    .co-comparison-wrap { overflow-x: auto; }
    .co-comparison-table {
        min-width: 1580px;
        table-layout: fixed;
        margin-bottom: 0;
    }
    .co-comparison-table th,
    .co-comparison-table td {
        vertical-align: middle;
        text-align: center;
        padding: .55rem .4rem;
    }
    .co-comparison-table .co-source-cell {
        width: 230px;
        text-align: left;
        padding-left: 1rem;
        position: sticky;
        left: 0;
        z-index: 2;
        background: var(--tblr-bg-surface);
    }
    .co-source-title { font-weight: 600; }
    .co-source-note { color: var(--tblr-secondary-color); font-size: .76rem; margin-top: .15rem; }
    .co-pref-head { min-width: 62px; font-size: .76rem; white-space: nowrap; }
    .co-choice-box {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 48px;
        height: 36px;
        padding: 0 .45rem;
        border: 1px solid var(--tblr-border-color);
        border-radius: .4rem;
        background: var(--tblr-bg-surface);
        font-weight: 500;
    }
    .co-choice-empty { color: var(--tblr-secondary-color); font-weight: 400; }
    .co-row-validated td { background: rgba(var(--tblr-success-rgb), .035); }
    .co-row-omr td { background: rgba(var(--tblr-purple-rgb), .025); }
    .co-row-validated .co-source-cell { background: color-mix(in srgb, var(--tblr-bg-surface) 96%, var(--tblr-success) 4%); }
    .co-row-omr .co-source-cell { background: color-mix(in srgb, var(--tblr-bg-surface) 97%, var(--tblr-purple) 3%); }
    .co-match { border-color: rgba(var(--tblr-success-rgb), .55); color: var(--tblr-success); }
    .co-different { border-color: rgba(var(--tblr-danger-rgb), .55); color: var(--tblr-danger); }

    .co-decision-option {
        position: relative;
        height: 100%;
        border: 1px solid var(--tblr-border-color);
        border-radius: .55rem;
        padding: 1rem 1rem 1rem 2.75rem;
        cursor: pointer;
        transition: border-color .16s ease, background-color .16s ease, box-shadow .16s ease;
    }
    .co-decision-option:hover { box-shadow: 0 .2rem .6rem rgba(0,0,0,.05); }
    .co-decision-option input {
        position: absolute;
        left: 1rem;
        top: 1.25rem;
    }
    .co-decision-yes { border-color: rgba(var(--tblr-success-rgb), .45); background: rgba(var(--tblr-success-rgb), .025); }
    .co-decision-no { border-color: rgba(var(--tblr-danger-rgb), .40); background: rgba(var(--tblr-danger-rgb), .02); }
    .co-decision-option:has(input:checked) { box-shadow: 0 0 0 2px rgba(var(--tblr-primary-rgb), .12); }
    .co-decision-yes:has(input:checked) { border-color: var(--tblr-success); background: rgba(var(--tblr-success-rgb), .055); }
    .co-decision-no:has(input:checked) { border-color: var(--tblr-danger); background: rgba(var(--tblr-danger-rgb), .05); }

    .co-review-toolbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .75rem;
        flex-wrap: wrap;
    }
    .co-review-count { font-weight: 600; }

    @media (max-width: 1199.98px) {
        .co-context-grid { grid-template-columns: repeat(3, minmax(150px, 1fr)); }
        .co-context-cell:nth-child(3) { border-right: 0; }
        .co-context-cell:nth-child(-n+3) { border-bottom: 1px solid var(--tblr-border-color); }
    }
    @media (max-width: 767.98px) {
        .co-context-grid { grid-template-columns: 1fr 1fr; }
        .co-context-cell { border-bottom: 1px solid var(--tblr-border-color); }
        .co-context-cell:nth-child(odd) { border-right: 1px solid var(--tblr-border-color); }
        .co-context-cell:nth-child(even) { border-right: 0; }
        .co-context-cell:nth-last-child(-n+2) { border-bottom: 0; }
    }
</style>

<div class="page-header d-print-none">
    <div class="container-xl">
        <div class="row align-items-center g-2">
            <div class="col">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <h2 class="page-title mb-0">OMR Decision Review</h2>
                    @if($remainingOperatorReviews > 0)
                        <span class="badge bg-yellow-lt">DECISION REQUIRED</span>
                    @endif
                </div>
                <div class="text-secondary mt-1">Batch #{{ $batch->id }} · {{ $batch->original_name }}</div>
            </div>
            <div class="col-auto ms-auto d-print-none d-flex gap-2">
                <a href="{{ request()->fullUrl() }}" class="btn btn-outline-primary">Refresh Results</a>
                <a href="{{ route('choice-optimization.index') }}" class="btn btn-outline-secondary">Back</a>
            </div>
        </div>
    </div>
</div>

<div class="page-body">
<div class="container-xl">
    <div class="row row-cards mb-3">
        @foreach([
            ['Status', strtoupper(str_replace('_',' ', $batch->status)), 'co-status'],
            ['Total', number_format($batch->total_rows), 'co-total'],
            ['Valid', number_format($batch->valid_rows), 'co-valid'],
            ['Invalid', number_format($batch->invalid_rows), 'co-invalid'],
            ['Conflict', number_format($batch->conflict_rows), 'co-conflict'],
            ['Decision Review', number_format($batch->review_rows ?? 0), 'co-review'],
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

    <div class="card mb-3" id="co-progress-card" @if(!in_array($batch->status, ['queued','processing','validation_queued','validating','approval_queued','approving'], true)) style="display:none" @endif>
        <div class="card-body">
            <div class="d-flex justify-content-between mb-2">
                <div><strong>Background processing</strong></div>
                <div id="co-progress-text">{{ number_format((float) $batch->progress_percent, 1) }}%</div>
            </div>
            <div class="progress progress-sm">
                <div id="co-progress-bar" class="progress-bar" style="width: {{ min(100, (float) $batch->progress_percent) }}%"></div>
            </div>
            <div class="text-secondary small mt-2">Progress is updated through JSON polling. This page does not auto-refresh.</div>
        </div>
    </div>

    <div class="card mb-3" id="co-validation-actions">
        <div class="card-body py-3">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div>
                    <div class="fw-semibold">OMR Choice Validation</div>
                    <div class="text-secondary small">
                        Re-run the full queued validation against the current finalized Circular, Registration eligibility and Written-qualified track.
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    @if(in_array((string) $batch->status, ['validated', 'needs_review', 'validation_failed'], true))
                        <form method="POST" action="{{ route('choice-optimization.omr.revalidate', $batch) }}" class="mb-0">
                            @csrf
                            <button class="btn btn-outline-primary" type="submit">
                                Re-validate OMR Choices
                            </button>
                        </form>
                    @elseif(in_array((string) $batch->status, ['validation_queued', 'validating'], true))
                        <button class="btn btn-outline-secondary" type="button" disabled>
                            Re-validation in progress…
                        </button>
                    @else
                        <span class="text-secondary small">Re-validation becomes available after the first validation run.</span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div id="co-review-complete" class="alert alert-success" @if($remainingOperatorReviews > 0) style="display:none" @endif>
        <div class="d-flex flex-wrap align-items-center gap-2 justify-content-between">
            <div><strong>Operator review complete.</strong> All review-required OMR rows have been resolved.</div>
            @if(in_array($batch->status, ['staged','needs_review','validation_failed'], true))
                <form method="POST" action="{{ route('choice-optimization.omr.validate', $batch) }}" class="co-revalidate-form mb-0">
                    @csrf
                    <button class="btn btn-success" type="submit">Queue OMR Re-validation</button>
                </form>
            @endif
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body py-3">
            <form class="row g-2 align-items-center" method="GET">
                <div class="col-md-3">
                    <select class="form-select" name="status">
                        @foreach([
                            'all'=>'All', 'conflict'=>'Conflict', 'decision_review'=>'Decision Review',
                            'invalid'=>'Invalid', 'pending'=>'Pending', 'valid'=>'Valid',
                        ] as $key=>$label)
                            <option value="{{ $key }}" @selected($status===$key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <input class="form-control" name="search" value="{{ $search }}" placeholder="Registration">
                </div>
                <div class="col-md-auto"><button class="btn btn-outline-primary">Filter</button></div>
                <div class="col-md ms-md-auto text-md-end text-secondary small">
                    @if($remainingOperatorReviews > 0)
                        <span class="co-review-count"><span id="co-review-remaining">{{ number_format($remainingOperatorReviews) }}</span> review(s) remaining</span>
                    @else
                        No operator review pending
                    @endif
                </div>
            </form>
        </div>
    </div>

    <div id="co-review-list" class="d-flex flex-column gap-3">
        @forelse($rows as $row)
            @php
                $rawChoices = collect((array) $row->raw_choices)->filter(fn($v) => filled($v))->values()->all();
                $validatedChoices = $row->registration_id ? ($validatedChoiceMap[(int) $row->registration_id] ?? []) : [];
                $registrationChoices = $row->registration_id ? ($registrationChoiceMap[(int) $row->registration_id] ?? []) : [];
                $candidateContext = $row->registration_id ? ($candidateContextMap[(int) $row->registration_id] ?? []) : [];
                $identityErrorCodes = ['INVALID_OMR_REGISTRATION','WRITTEN_REGISTRATION_AMBIGUOUS','DUPLICATE_OMR_REGISTRATION','OMR_REGISTRATION_REQUIRED'];
                $needsRegistrationResolution = in_array($row->validation_status, ['conflict','invalid'], true)
                    && collect((array)$row->validation_errors)->contains(fn($e) => in_array($e['code'] ?? '', $identityErrorCodes, true));
                $isReviewItem = $row->validation_status === 'decision_review' || $needsRegistrationResolution;
                $choiceSlotCount = max(
                    (int) ($batch->configured_maximum_choices ?? config('choice-optimization.omr_max_choices', 20)),
                    count($registrationChoices),
                    count($validatedChoices),
                    count($rawChoices),
                    1
                );
                $preferenceNumbers = range(1, $choiceSlotCount);
                $badge = match($row->validation_status) {
                    'valid' => 'bg-green-lt',
                    'conflict', 'decision_review' => 'bg-yellow-lt',
                    'pending' => 'bg-secondary-lt',
                    default => 'bg-red-lt',
                };
            @endphp

            <div class="card co-review-card {{ $isReviewItem ? 'co-review-item' : '' }}"
                 data-review-item="{{ $isReviewItem ? '1' : '0' }}"
                 data-row-id="{{ $row->id }}">

                <div class="card-header py-3">
                    <div class="co-review-toolbar w-100">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <span class="fw-semibold">Candidate Review</span>
                            <span class="text-secondary">Source Row {{ $row->source_row }}</span>
                            <span class="badge {{ $badge }}">{{ strtoupper(str_replace('_',' ', $row->validation_status)) }}</span>
                        </div>
                        @if($isReviewItem)
                            <span class="text-secondary small">Review this candidate, confirm the decision, then continue automatically.</span>
                        @endif
                    </div>
                </div>

                <div class="co-context-grid border-bottom">
                    <div class="co-context-cell">
                        <div class="co-context-label">Registration</div>
                        <div class="co-context-body">
                            <div class="co-context-value"><code>{{ $row->effective_reg ?: $row->raw_reg ?: '—' }}</code></div>
                            @if($row->effective_reg && $row->raw_reg && $row->effective_reg !== $row->raw_reg)
                                <div class="small text-secondary">Raw: <code>{{ $row->raw_reg }}</code></div>
                            @endif
                        </div>
                    </div>
                    <div class="co-context-cell">
                        <div class="co-context-label">Original Category</div>
                        <div class="co-context-body">
                            <div class="co-context-value"><span class="badge bg-blue-lt">{{ $candidateContext['category_code'] ?? '—' }}</span></div>
                        </div>
                    </div>
                    <div class="co-context-cell">
                        <div class="co-context-label">Written Qualified Track</div>
                        <div class="co-context-body">
                            <div class="co-context-value"><span class="badge bg-azure-lt">{{ $row->written_qualified_track ?: '—' }}</span></div>
                        </div>
                    </div>
                    <div class="co-context-cell">
                        <div class="co-context-label">OMR Decision</div>
                        <div class="co-context-body">
                            <div class="co-context-value">
                                <span class="badge {{ $row->change_choice === 'NO' ? 'bg-red-lt' : 'bg-green-lt' }}">{{ $row->change_choice ?: '—' }}</span>
                            </div>
                            <div class="small text-secondary">{{ $row->raw_choice_count }} option(s) found</div>
                        </div>
                    </div>
                    <div class="co-context-cell">
                        <div class="co-context-label">Status / Reason</div>
                        <div class="co-context-body">
                            <div><span class="badge {{ $badge }}">{{ strtoupper(str_replace('_',' ', $row->validation_status)) }}</span></div>
                            @if($row->choice_validation_status && $row->choice_validation_status !== 'not_started')
                                <div class="small text-secondary">Choice: {{ strtoupper(str_replace('_',' ', $row->choice_validation_status)) }}</div>
                            @endif
                        </div>
                    </div>
                    <div class="co-context-cell">
                        <div class="co-context-label">Effective Decision</div>
                        <div class="co-context-body">
                            <div class="co-context-value">{{ $row->effective_change_choice ?: '—' }}</div>
                            @if($row->decision_resolution)
                                <div class="small text-secondary">Resolved</div>
                            @endif
                        </div>
                    </div>
                </div>

                @if(!empty($row->validation_errors) || !empty($row->validation_warnings))
                    <div class="px-3 pt-3">
                        @foreach((array)$row->validation_errors as $error)
                            <div class="alert alert-danger py-2 mb-2">
                                <strong>{{ $error['code'] ?? 'ERROR' }}</strong>: {{ $error['message'] ?? '' }}
                            </div>
                        @endforeach
                        @foreach((array)$row->validation_warnings as $warning)
                            <div class="alert alert-warning py-2 mb-2">
                                <strong>{{ $warning['code'] ?? 'WARNING' }}</strong>: {{ $warning['message'] ?? '' }}
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap mb-2">
                        <div>
                            <h3 class="card-title mb-1">Choice Comparison</h3>
                            <div class="text-secondary small">Left to Right = Preference Order · #01 is the first preference</div>
                        </div>
                        <div class="text-secondary small">Total OMR Options: <strong>{{ count($rawChoices) }}</strong></div>
                    </div>

                    <div class="border rounded co-comparison-wrap mb-4">
                        <table class="table table-bordered co-comparison-table">
                            <thead>
                                <tr>
                                    <th class="co-source-cell">Choice Source</th>
                                    @foreach($preferenceNumbers as $pref)
                                        <th class="co-pref-head">#{{ str_pad((string)$pref, 2, '0', STR_PAD_LEFT) }}@if($pref === 1)<div class="text-secondary fw-normal">First</div>@endif</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach([
                                    ['Registration Choice', 'Original', $registrationChoices, ''],
                                    ['Finalized Validated Choice', 'After Choice Validation', $validatedChoices, 'co-row-validated'],
                                    ['OMR Choice / OMR Options', 'From OMR Form', $rawChoices, 'co-row-omr'],
                                ] as [$sourceLabel, $sourceNote, $choiceValues, $rowClass])
                                    <tr class="{{ $rowClass }}">
                                        <td class="co-source-cell">
                                            <div class="co-source-title">{{ $sourceLabel }}</div>
                                            <div class="co-source-note">{{ $sourceNote }}</div>
                                        </td>
                                        @foreach($preferenceNumbers as $pref)
                                            @php
                                                $choiceValue = $choiceValues[$pref - 1] ?? null;
                                                $validatedAtPref = $validatedChoices[$pref - 1] ?? null;
                                                $comparisonClass = '';
                                                if ($sourceLabel === 'OMR Choice / OMR Options' && filled($choiceValue)) {
                                                    $comparisonClass = ((string)$choiceValue === (string)$validatedAtPref) ? 'co-match' : 'co-different';
                                                }
                                            @endphp
                                            <td>
                                                <span class="co-choice-box {{ $comparisonClass }} {{ blank($choiceValue) ? 'co-choice-empty' : '' }}">
                                                    {{ filled($choiceValue) ? $choiceValue : '—' }}
                                                </span>
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @php
                        $validatedOmrChoices = collect((array) $row->validated_omr_choice_codes)->filter(fn($v) => filled($v))->values()->all();
                        $omrChoiceStatus = strtolower((string) ($row->choice_validation_status ?: 'pending'));
                        $omrChoiceDetails = (array) ($row->choice_validation_details ?? []);
                    @endphp
                    <div class="border rounded mb-4">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 px-3 py-2 border-bottom bg-light">
                            <div>
                                <div class="fw-semibold">OMR Choice Validation Result</div>
                                <div class="text-secondary small">Derived validation output only; raw OMR remains unchanged.</div>
                            </div>
                            @if($omrChoiceStatus === 'valid' && count($validatedOmrChoices) > 0)
                                <span class="badge bg-green-lt text-green">VALIDATED / DOWNSTREAM SAFE</span>
                            @elseif($omrChoiceStatus === 'invalid')
                                <span class="badge bg-red-lt text-red">INVALID / NOT DOWNSTREAM SAFE</span>
                            @else
                                <span class="badge bg-yellow-lt text-yellow">{{ strtoupper(str_replace('_', ' ', $omrChoiceStatus)) }}</span>
                            @endif
                        </div>
                        <div class="p-3">
                            <div class="row g-3">
                                <div class="col-lg-6">
                                    <div class="text-secondary small fw-semibold mb-2">Raw OMR Choice</div>
                                    <div class="d-flex flex-wrap gap-2">
                                        @forelse($rawChoices as $index => $code)
                                            <span class="badge bg-secondary-lt text-secondary">#{{ str_pad((string)($index + 1), 2, '0', STR_PAD_LEFT) }} {{ $code }}</span>
                                        @empty
                                            <span class="text-secondary small">No OMR options supplied.</span>
                                        @endforelse
                                    </div>
                                </div>
                                <div class="col-lg-6">
                                    <div class="text-secondary small fw-semibold mb-2">Expanded / Validated OMR Choice</div>
                                    <div class="d-flex flex-wrap gap-2">
                                        @forelse($validatedOmrChoices as $index => $code)
                                            <span class="badge bg-green-lt text-green">#{{ str_pad((string)($index + 1), 2, '0', STR_PAD_LEFT) }} {{ $code }}</span>
                                        @empty
                                            <span class="text-secondary small">No clean validated OMR override is available.</span>
                                        @endforelse
                                    </div>
                                </div>
                            </div>

                            <div class="row g-3 mt-1">
                                <div class="col-lg-4">
                                    <div class="h-100 border rounded p-3">
                                        <div class="fw-semibold mb-2">Validation Errors</div>
                                        @forelse((array) $row->validation_errors as $error)
                                            <div class="small text-danger mb-1">
                                                <strong>{{ $error['code'] ?? 'ERROR' }}</strong>
                                                @if(!empty($error['message'])) — {{ $error['message'] }} @endif
                                            </div>
                                        @empty
                                            <div class="small text-success">No blocking validation error.</div>
                                        @endforelse
                                    </div>
                                </div>
                                <div class="col-lg-4">
                                    <div class="h-100 border rounded p-3">
                                        <div class="fw-semibold mb-2">Warnings / Review Notes</div>
                                        @forelse((array) $row->validation_warnings as $warning)
                                            <div class="small text-warning mb-1">
                                                <strong>{{ $warning['code'] ?? 'WARNING' }}</strong>
                                                @if(!empty($warning['message'])) — {{ $warning['message'] }} @endif
                                            </div>
                                        @empty
                                            <div class="small text-secondary">No validation warning.</div>
                                        @endforelse
                                    </div>
                                </div>
                                <div class="col-lg-4">
                                    <div class="h-100 border rounded p-3">
                                        <div class="fw-semibold mb-2">Expansion / Removal Details</div>
                                        @if($omrChoiceDetails !== [])
                                            <pre class="small mb-0" style="white-space:pre-wrap">{{ json_encode($omrChoiceDetails, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) }}</pre>
                                        @else
                                            <div class="small text-secondary">No expansion/removal detail recorded.</div>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <div class="alert {{ $omrChoiceStatus === 'valid' && count($validatedOmrChoices) > 0 ? 'alert-success' : 'alert-warning' }} mt-3 mb-0">
                                @if($omrChoiceStatus === 'valid' && count($validatedOmrChoices) > 0)
                                    <strong>OMR Choice is fully validated and safe for downstream use.</strong>
                                @else
                                    <strong>OMR Choice is NOT eligible for override / Allocation until validation completes successfully.</strong>
                                @endif
                            </div>
                        </div>
                    </div>

                    @if($row->validation_status === 'decision_review')
                        <div class="border rounded p-3 bg-light-subtle">
                            <div class="d-flex align-items-center gap-2 mb-3">
                                <h3 class="card-title mb-0">Operator Decision</h3>
                                <span class="badge bg-red-lt">This action is final for this review cycle</span>
                            </div>

                            <form method="POST" action="{{ route('choice-optimization.omr.resolve-decision', $row) }}" class="co-review-form">
                                @csrf
                                <div class="row g-3 mb-3">
                                    <div class="col-lg-6">
                                        <label class="co-decision-option co-decision-yes d-block">
                                            <input class="form-check-input" type="radio" name="resolution" value="consider_no_as_yes_keep_options" required>
                                            <div class="fw-semibold text-success">Consider NO as YES and keep the OMR options</div>
                                            <div class="text-secondary small mt-1">Treat this as a change request. The OMR options will be re-validated against current rules and eligibility.</div>
                                        </label>
                                    </div>
                                    <div class="col-lg-6">
                                        <label class="co-decision-option co-decision-no d-block">
                                            <input class="form-check-input" type="radio" name="resolution" value="keep_no_discard_options" required>
                                            <div class="fw-semibold text-danger">Consider NO as NO and discard the OMR options</div>
                                            <div class="text-secondary small mt-1">Keep the current finalized validated choice and ignore the OMR options for the effective choice.</div>
                                        </label>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Administrative reason / observation <span class="text-danger">*</span></label>
                                    <textarea class="form-control" rows="2" name="reason" maxlength="500" placeholder="Enter reason for your decision..." required></textarea>
                                </div>

                                <div class="d-flex align-items-center justify-content-end gap-2 flex-wrap">
                                    <span class="small text-secondary me-auto co-save-state"></span>
                                    <button class="btn btn-primary" type="submit">Confirm & Continue</button>
                                </div>
                            </form>
                        </div>
                    @elseif($needsRegistrationResolution)
                        <div class="border rounded p-3 bg-light-subtle">
                            <h3 class="card-title mb-3">Registration Conflict Resolution</h3>
                            <form method="POST" action="{{ route('choice-optimization.omr.resolve-registration', $row) }}" class="row g-2 co-review-form">
                                @csrf
                                <div class="col-md-4">
                                    <label class="form-label">Correct Registration</label>
                                    <input class="form-control" name="effective_reg" value="{{ $row->effective_reg }}" placeholder="Correct registration" required>
                                </div>
                                <div class="col-md">
                                    <label class="form-label">Administrative reason</label>
                                    <input class="form-control" name="reason" placeholder="Reason for correction" required>
                                </div>
                                <div class="col-md-auto d-flex align-items-end">
                                    <button class="btn btn-primary" type="submit">Save & Continue</button>
                                </div>
                                <div class="col-12"><span class="small text-secondary co-save-state"></span></div>
                            </form>
                        </div>
                    @elseif($row->decision_resolution)
                        <div class="alert alert-info mb-0">
                            <strong>Decision resolved:</strong> {{ str_replace('_', ' ', strtoupper($row->decision_resolution)) }}
                            @if($row->decision_resolution_reason)
                                <div class="small mt-1">{{ $row->decision_resolution_reason }}</div>
                            @endif
                        </div>
                    @elseif($row->resolution_status === 'resolved')
                        <div class="alert alert-info mb-0">
                            <strong>Registration resolved.</strong>
                            @if($row->resolution_reason)<div class="small mt-1">{{ $row->resolution_reason }}</div>@endif
                        </div>
                    @elseif($row->effective_change_choice === 'YES' && $row->choice_validation_status === 'valid')
                        <div class="alert alert-success mb-0">
                            <strong>Validated OMR Override:</strong>
                            <code class="ms-1">{{ implode(' ', (array) $row->validated_omr_choice_codes) }}</code>
                        </div>
                    @else
                        <div class="text-secondary">No operator action required for this row.</div>
                    @endif
                </div>
            </div>
        @empty
            <div class="card"><div class="card-body text-center text-secondary py-5">No OMR rows found.</div></div>
        @endforelse
    </div>

    @if($rows->hasPages())
        <div class="mt-3">{{ $rows->links() }}</div>
    @endif
</div>
</div>

<script>
(() => {
    const runningStates = ['queued','processing','validation_queued','validating','approval_queued','approving'];
    let active = runningStates.includes(@json($batch->status));
    const url = @json(route('choice-optimization.omr.status', $batch));
    const card = document.getElementById('co-progress-card');
    const bar = document.getElementById('co-progress-bar');
    const text = document.getElementById('co-progress-text');
    const fmt = new Intl.NumberFormat();

    const set = (id, value) => {
        const el = document.getElementById(id);
        if (el) el.textContent = value;
    };

    const poll = async () => {
        if (!active) return;
        try {
            const response = await fetch(url, {headers: {'Accept': 'application/json'}, cache: 'no-store'});
            if (!response.ok) throw new Error('Unable to read processing status.');
            const data = await response.json();
            const pct = Math.max(0, Math.min(100, Number(data.progress_percent || 0)));
            if (card) card.style.display = '';
            if (bar) bar.style.width = pct + '%';
            if (text) text.textContent = pct.toFixed(1) + '%';
            set('co-status', String(data.status || '').replaceAll('_',' ').toUpperCase());
            set('co-total', fmt.format(data.total_rows || 0));
            set('co-valid', fmt.format(data.valid_rows || 0));
            set('co-invalid', fmt.format(data.invalid_rows || 0));
            set('co-conflict', fmt.format(data.conflict_rows || 0));
            set('co-review', fmt.format(data.review_rows || 0));

            if (data.running) window.setTimeout(poll, 1500);
            else active = false;
        } catch (error) {
            window.setTimeout(poll, 3000);
        }
    };

    const reviewRows = () => Array.from(document.querySelectorAll('.co-review-item:not(.co-resolved)'));

    const updateRemaining = (remaining = null) => {
        const count = remaining === null ? reviewRows().length : Number(remaining);
        const el = document.getElementById('co-review-remaining');
        if (el) el.textContent = fmt.format(Math.max(0, count));
        return count;
    };

    const focusNextReview = () => {
        const next = reviewRows()[0];
        document.querySelectorAll('.co-review-item').forEach(row => row.classList.remove('co-current-review'));
        if (!next) return;
        next.classList.add('co-current-review');
        next.scrollIntoView({behavior: 'smooth', block: 'start'});
        const target = next.querySelector('input[type="radio"], input:not([type="hidden"]), select, button');
        if (target) window.setTimeout(() => target.focus({preventScroll: true}), 300);
    };

    const revealReviewComplete = () => {
        const complete = document.getElementById('co-review-complete');
        if (complete) complete.style.display = '';
        updateRemaining(0);
        complete?.scrollIntoView({behavior: 'smooth', block: 'center'});
    };

    document.querySelectorAll('.co-review-form').forEach(form => {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (!form.reportValidity()) return;

            const row = form.closest('.co-review-item');
            const button = form.querySelector('button[type="submit"]');
            const state = form.querySelector('.co-save-state');
            const originalText = button?.textContent || '';

            row?.classList.add('co-resolving');
            if (button) { button.disabled = true; button.textContent = 'Saving…'; }
            if (state) { state.textContent = 'Saving operator decision…'; state.classList.remove('text-danger'); }

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: new FormData(form),
                    headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
                });
                const data = await response.json().catch(() => ({}));
                if (!response.ok) {
                    const validation = data.errors ? Object.values(data.errors).flat().join(' ') : '';
                    throw new Error(validation || data.message || 'Unable to save operator decision.');
                }

                if (state) state.textContent = 'Saved';
                row?.classList.remove('co-resolving');
                row?.classList.add('co-resolved');

                window.setTimeout(() => {
                    if (row) row.style.display = 'none';
                    const remaining = updateRemaining(data.remaining_review_rows ?? null);
                    if (remaining <= 0) revealReviewComplete();
                    else focusNextReview();
                }, 260);
            } catch (error) {
                row?.classList.remove('co-resolving');
                if (button) { button.disabled = false; button.textContent = originalText; }
                if (state) { state.textContent = error.message || 'Save failed.'; state.classList.add('text-danger'); }
            }
        });
    });

    if (reviewRows().length > 0) focusNextReview();
    if (active) window.setTimeout(poll, 800);
})();
</script>
@endsection
