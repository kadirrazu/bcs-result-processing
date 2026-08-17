@extends('layouts.app')
@section('title','Choice Validation Detail')

@section('page-header')
<div class="row g-2 align-items-center">
    <div class="col">
        <h2 class="page-title">Choice Validation Detail</h2>
        <div class="text-secondary">
            {{ $result->registration?->name ?: 'Name not available' }}
            · Reg {{ $result->reg }}
            · User {{ $result->user_id ?: '—' }}
            · Validation v{{ $result->validation_version }}
        </div>
    </div>
    <div class="col-auto ms-auto">
        <a href="{{ route('choice-validation.results') }}" class="btn btn-outline-secondary">Back to Results</a>
    </div>
</div>
@endsection

@section('content')
<div class="card mb-3">
    <div class="card-header"><h3 class="card-title">Candidate &amp; Validation Summary</h3></div>
    <div class="card-body">
        @php
            $registrationCategory = $result->registration?->cadre_category;
            $registrationCategoryCode = is_object($registrationCategory) && method_exists($registrationCategory, 'code')
                ? $registrationCategory->code()
                : (string) ($registrationCategory ?? '—');
            $registrationCategoryLabel = is_object($registrationCategory) && method_exists($registrationCategory, 'label')
                ? $registrationCategory->label()
                : null;

            $writtenTrack = $writtenResult?->written_qualified_track;
            $writtenTrackValue = is_object($writtenTrack) && property_exists($writtenTrack, 'value')
                ? $writtenTrack->value
                : (string) ($writtenTrack ?? $result->written_qualified_track ?? '—');

            $currentTrackLabel = match(strtolower((string) $result->effective_track)) {
                'general' => 'General',
                'technical' => 'Technical',
                'both' => 'General & Technical',
                default => '—',
            };
        @endphp

        <div class="row g-3 align-items-start">
            <div class="col-md-2">
                <div class="text-secondary small">Registration / User ID</div>
                <div class="fw-semibold">{{ $result->reg }}</div>
                <div class="small text-secondary">{{ $result->user_id ?: '—' }}</div>
            </div>

            <div class="col-md-3">
                <div class="text-secondary small">Candidate Name</div>
                <div class="fw-semibold">{{ $result->registration?->name ?: '—' }}</div>
                @if($result->registration?->name_bn)
                    <div class="small text-secondary">{{ $result->registration->name_bn }}</div>
                @endif
            </div>

            <div class="col-md-2">
                <div class="text-secondary small">Original Category</div>
                <div class="fw-semibold">{{ $registrationCategoryCode }}</div>
                @if($registrationCategoryLabel)
                    <div class="small text-secondary">{{ $registrationCategoryLabel }}</div>
                @endif
                <div class="small text-secondary">Registration</div>
            </div>

            <div class="col-md-2">
                <div class="text-secondary small">Derived Category after Written</div>
                <div class="fw-semibold">{{ $writtenTrackValue }}</div>
                <div class="small text-secondary">Finalized Written Result</div>
            </div>

            <div class="col-md-3">
                <div class="text-secondary small">Current Track</div>
                <div class="fw-semibold">{{ strtoupper($result->effective_track ?? '—') }}</div>
                <div class="small text-secondary">{{ $currentTrackLabel }}</div>
            </div>

            <div class="col-12">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="text-secondary small">Validation Status:</span>
                    <span class="badge {{ \App\Support\ChoiceValidationStatusPresenter::resultBadgeClass($result->status) }}">
                        {{ \App\Support\ChoiceValidationStatusPresenter::resultLabel($result->status) }}
                    </span>
                    @if($result->result_reason_code)
                        <span class="small text-secondary">{{ $result->result_reason_code }}</span>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header">
        <div>
            <h3 class="card-title">Choice Comparison</h3>
            <div class="card-subtitle">Original source is preserved. Removed choices are red; expanded/derived choices are blue.</div>
        </div>
    </div>
    <div class="card-body">
        <div class="mb-3">
            <div class="text-secondary small mb-2">Original Imported Choices</div>
            <div class="d-flex gap-2 flex-wrap">
                @forelse($originalChoices as $choice)
                    @php
                        $resolution = $resolutionByPosition[$choice['position']] ?? ['visual' => 'retained', 'outputs' => [], 'reason_codes' => []];
                        $effectiveCodeAtPosition = collect($effectiveChoices)->firstWhere('position', $choice['position'])['code'] ?? null;
                        $wasCorrected = $effectiveCodeAtPosition !== null && (string)$effectiveCodeAtPosition !== (string)$choice['code'];

                        $choiceClass = match($resolution['visual']) {
                            'removed' => 'bg-red-lt text-red border border-red',
                            'expanded' => 'bg-blue-lt text-blue border border-blue',
                            default => 'bg-green-lt text-green border border-green',
                        };
                    @endphp
                    <span class="badge {{ $wasCorrected ? 'bg-yellow-lt text-yellow border border-yellow' : $choiceClass }} px-2 py-2"
                          title="{{ $choice['column'] }}">
                        {{ $choice['code'] }}
                        @if($wasCorrected)
                            <span class="ms-1">→ {{ $effectiveCodeAtPosition }}</span>
                            <span class="ms-1">Corrected</span>
                        @elseif($resolution['visual'] === 'removed')
                            <span class="ms-1">Removed</span>
                        @elseif($resolution['visual'] === 'expanded')
                            <span class="ms-1">Expanded</span>
                        @endif
                    </span>
                @empty
                    <span class="text-secondary">No original choices.</span>
                @endforelse
            </div>
        </div>

        @if($hasEffectiveCorrection)
        <div class="mb-3">
            <div class="text-secondary small mb-2">Effective Choices After Manual Correction</div>
            <div class="d-flex gap-2 flex-wrap">
                @foreach($effectiveChoices as $choice)
                    @php
                        $resolution = $resolutionByPosition[$choice['position']] ?? ['visual' => 'retained'];
                        $choiceClass = match($resolution['visual']) {
                            'removed' => 'bg-red-lt text-red border border-red',
                            'expanded' => 'bg-blue-lt text-blue border border-blue',
                            default => 'bg-green-lt text-green border border-green',
                        };
                    @endphp
                    <span class="badge {{ $choiceClass }} px-2 py-2" title="{{ $choice['column'] }}">
                        {{ $choice['code'] }}
                        @if($resolution['visual'] === 'removed')
                            <span class="ms-1">Removed</span>
                        @elseif($resolution['visual'] === 'expanded')
                            <span class="ms-1">Expanded</span>
                        @endif
                    </span>
                @endforeach
            </div>
        </div>
        @endif

        <div>
            <div class="text-secondary small mb-2">Validated Choices</div>
            <div class="d-flex gap-2 flex-wrap">
                @forelse((array)$result->validated_choice_codes as $code)
                    @php $isExpandedOutput = in_array((string)$code, $expandedOutputCodes, true); @endphp
                    <span class="badge {{ $isExpandedOutput ? 'bg-blue-lt text-blue border border-blue' : 'bg-green-lt text-green border border-green' }} px-2 py-2">
                        {{ $code }}
                        @if($isExpandedOutput)<span class="ms-1">Derived / Expanded</span>@endif
                    </span>
                @empty
                    <span class="text-secondary">No validated choices.</span>
                @endforelse
            </div>
        </div>

        <div class="mt-3 small text-secondary">
            <span class="badge bg-red-lt text-red me-1">Removed</span>
            <span class="badge bg-blue-lt text-blue me-1">Expanded / Derived</span>
            <span class="badge bg-yellow-lt text-yellow">Manually Corrected</span>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header">
        <div>
            <h3 class="card-title">Source Choice Resolution Trail</h3>
            <div class="card-subtitle">Changed choices are highlighted; unchanged retained choices remain neutral.</div>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead>
                <tr><th>Source</th><th>Code</th><th>Resolved</th><th>Result</th><th>Output</th><th>Reason</th></tr>
            </thead>
            <tbody>
            @foreach($result->items as $item)
                @php
                    $rowClass = match(strtolower((string)$item->result)) {
                        'removed' => 'table-danger',
                        'expanded' => 'table-info',
                        default => '',
                    };
                @endphp
                <tr class="{{ $rowClass }}">
                    <td>{{ $item->source_column }}</td>
                    <td><code>{{ $item->source_code }}</code></td>
                    <td>{{ strtoupper($item->resolved_type) }}</td>
                    <td>
                        @if(strtolower((string)$item->result) === 'removed')
                            <span class="badge bg-red-lt text-red">REMOVED</span>
                        @elseif(strtolower((string)$item->result) === 'expanded')
                            <span class="badge bg-blue-lt text-blue">EXPANDED</span>
                        @else
                            <span class="badge bg-green-lt text-green">{{ strtoupper($item->result) }}</span>
                        @endif
                    </td>
                    <td>
                        @if($item->output_code)
                            <code>#{{ $item->output_position }} {{ $item->output_code }}</code>
                        @else
                            —
                        @endif
                    </td>
                    <td>
                        @if($item->reason_code)
                            <strong>{{ $item->reason_code }}</strong>
                            <div class="small text-secondary">{{ $item->reason_message }}</div>
                        @else
                            <span class="text-secondary">{{ $item->reason_message }}</span>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header">
        <div>
            <h3 class="card-title">Audited Manual Choice Correction</h3>
            <div class="card-subtitle">Original imported source remains preserved. Saving creates a correction overlay and immediately revalidates this candidate.</div>
        </div>
    </div>
    <form method="POST" action="{{ route('choice-validation.result.correct',$result) }}">
        @csrf
        <div class="card-body">
            <div class="row g-2">
                @foreach($choiceColumns as $column)
                    <div class="col-6 col-sm-4 col-md-3 col-lg-2">
                        <label class="form-label">{{ $column }}</label>
                        <input type="text" class="form-control @error($column) is-invalid @enderror" name="{{ $column }}" value="{{ old($column,$effectiveSnapshot[$column] ?? '') }}">
                        @error($column)<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                @endforeach
            </div>
            <div class="mt-3">
                <label class="form-label required">Correction Reason</label>
                <textarea name="reason" rows="2" class="form-control @error('reason') is-invalid @enderror" required>{{ old('reason') }}</textarea>
                @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
        <div class="card-footer text-end">
            <button class="btn btn-warning">Save Correction & Revalidate Candidate</button>
        </div>
    </form>
</div>

<div class="card">
    <div class="card-header">
        <div>
            <h3 class="card-title">Manual Correction History</h3>
            <div class="card-subtitle">Every actual edit shows the exact old → new Choice value, reason and operator.</div>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead><tr><th>Time</th><th>Operator</th><th>Old → New</th><th>Reason</th><th>Revalidated</th></tr></thead>
            <tbody>
            @forelse($corrections as $correction)
                <tr>
                    <td class="text-nowrap">{{ optional($correction->created_at)->format('Y-m-d H:i:s') }}</td>
                    <td>{{ $correction->actor_name ?: $correction->actor_id }}</td>
                    <td>
                        <div class="d-flex flex-column gap-1">
                            @foreach((array)$correction->changed_positions as $position)
                                @php
                                    $column = 'opt_'.str_pad((string)$position,2,'0',STR_PAD_LEFT);
                                    $oldValue = $correction->before_snapshot[$column] ?? null;
                                    $newValue = $correction->corrected_snapshot[$column] ?? null;
                                @endphp
                                <div>
                                    <span class="text-secondary">{{ $column }}</span>
                                    <code class="ms-1">{{ filled($oldValue) ? $oldValue : '∅' }}</code>
                                    <span class="mx-1">→</span>
                                    <code>{{ filled($newValue) ? $newValue : '∅' }}</code>
                                </div>
                            @endforeach
                        </div>
                    </td>
                    <td style="min-width:220px">{{ $correction->reason }}</td>
                    <td>
                        @if($correction->revalidated_at)
                            <span class="badge bg-success-lt">YES</span>
                            <div class="small text-secondary">{{ $correction->revalidated_at->format('Y-m-d H:i:s') }}</div>
                        @else
                            <span class="badge bg-warning-lt">PENDING</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-secondary py-4">No manual correction history.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
