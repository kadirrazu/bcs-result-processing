@extends('layouts.app')

@section('content')
@php
    $prelimStatus = strtoupper((string) ($preliminary?->result_status?->value ?? $preliminary?->result_status ?? ''));
    $generalWrittenStatus = strtoupper((string) ($written->general_result_status?->value ?? $written->general_result_status ?? ''));
    $technicalWrittenStatus = strtoupper((string) ($written->technical_result_status?->value ?? $written->technical_result_status ?? ''));
    $vivaStatus = strtoupper((string) ($viva->viva_result_status?->value ?? $viva->viva_result_status ?? ''));
    $statusBadge = static fn (?string $status): string => match (strtoupper((string) $status)) {
        'PASS' => 'bg-green-lt text-green',
        'FAIL' => 'bg-red-lt text-red',
        default => 'bg-secondary-lt text-secondary',
    };
    $sourceSnapshot = (array) ($result->run?->source_snapshot ?? []);
    $isFinalizedView = ($state?->status === 'finalized');
    $originalChoiceKeys = array_map('strval', $originalChoiceCodes);
    $validatedChoiceKeys = array_map('strval', $validatedChoiceCodes);
    $originalChoiceStatus = static fn ($code): string => in_array((string) $code, $validatedChoiceKeys, true)
        ? 'RETAINED'
        : 'REMOVED';
    $validatedChoiceStatus = static fn ($code): string => in_array((string) $code, $originalChoiceKeys, true)
        ? 'RETAINED'
        : 'ADDED_OR_CORRECTED';
    $choiceCellClass = static fn (string $status): string => match ($status) {
        'REMOVED' => 'bg-red-lt text-red',
        'RETAINED' => 'bg-green-lt text-green',
        'ADDED_OR_CORRECTED' => 'bg-yellow-lt text-yellow',
        default => '',
    };
@endphp

<div class="page-header">
    <div class="container-xl">
        <div class="row align-items-center">
            <div class="col">
                <h2 class="page-title">{{ $isFinalizedView ? 'Individual Finalized Merit' : 'Individual Merit Review' }}</h2>
                <div class="text-secondary">REG {{ $result->reg }} · Version {{ $result->processing_version }} · <span class="badge bg-{{ $isFinalizedView ? 'success' : 'azure' }}-lt">{{ $isFinalizedView ? 'FINALIZED' : 'REVIEW_READY' }}</span></div>
            </div>
            <div class="col-auto d-flex gap-2">
                @if($isFinalizedView)
                    <a class="btn btn-outline-danger" href="{{ route('merit.pdf', $result) }}">Download PDF</a>
                @endif
                <a class="btn btn-outline-secondary" href="{{ route('merit.results',['run'=>$result->processing_run_id]) }}">Back</a>
            </div>
        </div>
    </div>
</div>

<div class="page-body">
    <div class="container-xl">
        <div class="mb-4">
            <div class="mb-3">
                <h3 class="mb-1">Upstream Finalized Data</h3>
                <div class="text-secondary">Authoritative candidate data used before Merit Generation.</div>
            </div>
            <div class="row g-3">
                <div class="col-lg-6 d-flex"><div class="card w-100 h-100"><div class="card-header py-2"><h4 class="card-title mb-0">Registration</h4></div><div class="list-group list-group-flush">
                    <div class="list-group-item py-2"><div class="row g-2"><div class="col-6 text-secondary">Name</div><div class="col-6 fw-medium">{{ $registration->name }}</div></div></div>
                    <div class="list-group-item py-2"><div class="row g-2"><div class="col-6 text-secondary">REG</div><div class="col-6">{{ $registration->reg }}</div></div></div>
                    <div class="list-group-item py-2"><div class="row g-2"><div class="col-6 text-secondary">USER</div><div class="col-6">{{ $registration->user_id }}</div></div></div>
                    <div class="list-group-item py-2"><div class="row g-2"><div class="col-6 text-secondary">Category</div><div class="col-6">{{ $registration->cadre_category ? ($registration->cadre_category->value.' - '.$registration->cadre_category->code()) : '—' }}</div></div></div>
                    <div class="list-group-item py-2"><div class="row g-2"><div class="col-6 text-secondary">Bachelor Subject</div><div class="col-6">{{ $bachelorSubjectDisplay }}</div></div></div>
                    <div class="list-group-item py-2"><div class="row g-2"><div class="col-6 text-secondary">Post Related Subject (PRS)</div><div class="col-6">{{ $postRelatedSubjectDisplay }}</div></div></div>
                    <div class="list-group-item py-2"><div class="row g-2"><div class="col-6 text-secondary">Birth Date</div><div class="col-6">{{ $registration->birth_date?->format('Y-m-d') ?? '—' }}</div></div></div>
                    <div class="list-group-item py-2"><div class="row g-2"><div class="col-6 text-secondary">Graduation Year</div><div class="col-6">{{ $registration->graduation_year ?? '—' }}</div></div></div>
                </div></div></div>

                <div class="col-lg-6 d-flex"><div class="card w-100 h-100"><div class="card-header py-2"><h4 class="card-title mb-0">Preliminary</h4></div><div class="list-group list-group-flush">
                    <div class="list-group-item py-2"><div class="row g-2"><div class="col-6 text-secondary">Mark</div><div class="col-6">{{ $preliminary?->mark ?? '—' }}</div></div></div>
                    <div class="list-group-item py-2"><div class="row g-2"><div class="col-6 text-secondary">Result</div><div class="col-6">@if($prelimStatus)<span class="badge {{ $statusBadge($prelimStatus) }}">{{ $prelimStatus }}</span>@else—@endif</div></div></div>
                </div></div></div>

                <div class="col-lg-6 d-flex"><div class="card w-100 h-100"><div class="card-header py-2"><h4 class="card-title mb-0">Written</h4></div><div class="list-group list-group-flush">
                    <div class="list-group-item py-2"><div class="row g-2"><div class="col-6 text-secondary">Qualified Track</div><div class="col-6 fw-medium">{{ strtoupper((string) ($written->written_qualified_track?->value ?? $written->written_qualified_track ?? '—')) }}</div></div></div>
                    <div class="list-group-item py-2"><div class="row g-2"><div class="col-6 text-secondary">General Counted</div><div class="col-6">{{ number_format((float)$written->general_counted_total,2) }} @if($generalWrittenStatus)<span class="badge {{ $statusBadge($generalWrittenStatus) }} ms-1">{{ $generalWrittenStatus }}</span>@endif</div></div></div>
                    <div class="list-group-item py-2"><div class="row g-2"><div class="col-6 text-secondary">Technical Counted</div><div class="col-6">{{ number_format((float)$written->technical_counted_total,2) }} @if($technicalWrittenStatus)<span class="badge {{ $statusBadge($technicalWrittenStatus) }} ms-1">{{ $technicalWrittenStatus }}</span>@endif</div></div></div>
                </div></div></div>

                <div class="col-lg-6 d-flex"><div class="card w-100 h-100"><div class="card-header py-2"><h4 class="card-title mb-0">Viva</h4></div><div class="list-group list-group-flush">
                    <div class="list-group-item py-2"><div class="row g-2"><div class="col-6 text-secondary">Attendance</div><div class="col-6">{{ strtoupper((string)$viva->attendance_status) }}</div></div></div>
                    <div class="list-group-item py-2"><div class="row g-2"><div class="col-6 text-secondary">Mark</div><div class="col-6">{{ $viva->mark }}</div></div></div>
                    <div class="list-group-item py-2"><div class="row g-2"><div class="col-6 text-secondary">Result</div><div class="col-6">@if($vivaStatus)<span class="badge {{ $statusBadge($vivaStatus) }}">{{ $vivaStatus }}</span>@else—@endif</div></div></div>
                </div></div></div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><div><h3 class="card-title">Finalized Tabulation Ranking Inputs</h3><div class="card-subtitle">Merit ranking reads these academic values from finalized Tabulation only.</div></div></div>
            <div class="table-responsive"><table class="table table-vcenter mb-0"><tbody>
                <tr><th class="w-40">Written Qualified Track</th><td>{{ strtoupper((string)$tabulation->written_qualified_track) }}</td></tr>
                <tr><th>Preliminary Mark</th><td>{{ $tabulation->preliminary_mark ?? '—' }}</td></tr>
                <tr><th>General / Technical Written</th><td>{{ $tabulation->general_written_total ?? '—' }} / {{ $tabulation->technical_written_total ?? '—' }}</td></tr>
                <tr><th>Viva Mark</th><td>{{ $tabulation->viva_mark }}</td></tr>
                <tr><th>General / Technical Grand Total</th><td>{{ $tabulation->generalGrandTotalDisplay() }} / {{ $tabulation->technicalGrandTotalDisplay() }}</td></tr>
                <tr><th>General / Technical Merit Eligible</th><td>{{ $tabulation->general_merit_eligible ? 'YES' : 'NO' }} / {{ $tabulation->technical_merit_eligible ? 'YES' : 'NO' }}</td></tr>
                <tr><th>Birth Date Snapshot</th><td>{{ $tabulation->birth_date?->format('Y-m-d') ?? '—' }}</td></tr>
                <tr><th>Graduation Year Snapshot</th><td>{{ $tabulation->graduation_year ?? '—' }}</td></tr>
                <tr><th>Tabulation Version</th><td>v{{ $tabulation->processing_version }}</td></tr>
            </tbody></table></div>
        </div>

        <div class="card mb-3">
            <div class="card-header">
                <div>
                    <h3 class="card-title">Finalized Choice Validation</h3>
                    <div class="card-subtitle">Original source choices and finalized validated choices are shown position-by-position for operator verification.</div>
                    <div class="mt-2 d-flex flex-wrap gap-2 small">
                        <span class="badge bg-green-lt text-green">RETAINED</span>
                        <span class="badge bg-red-lt text-red">REMOVED</span>
                        <span class="badge bg-yellow-lt text-yellow">ADDED_OR_CORRECTED</span>
                    </div>
                </div>
            </div>
            <div class="card-body border-bottom">
                <div class="row g-3">
                    <div class="col-md-4"><div class="text-secondary">Validation Version</div><div class="fw-semibold">{{ $choiceValidation?->validation_version ?? '—' }}</div></div>
                    <div class="col-md-4"><div class="text-secondary">Status</div><div class="fw-semibold">{{ strtoupper((string)($choiceValidation?->status ?? '—')) }}</div></div>
                    <div class="col-md-4"><div class="text-secondary">Effective Track</div><div class="fw-semibold">{{ strtoupper((string)($choiceValidation?->effective_track ?? '—')) }}</div></div>
                </div>
            </div>

            <div class="card-body border-bottom">
                <h4 class="mb-2">Original Choice</h4>
                <div class="text-secondary small mb-2">Raw imported choice sequence before validation/correction effects.</div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered text-center align-middle mb-0 merit-choice-position-table">
                        <tbody>
                            <tr>
                                <th class="text-start bg-light position-sticky start-0">Order</th>
                                @forelse($originalChoiceCodes as $choiceIndex => $choiceCode)
                                    @php($choiceVisualStatus = $originalChoiceStatus($choiceCode))
                                    <th class="{{ $choiceCellClass($choiceVisualStatus) }}" title="{{ $choiceVisualStatus }}">{{ $choiceIndex + 1 }}</th>
                                @empty
                                    <td>—</td>
                                @endforelse
                            </tr>
                            <tr>
                                <th class="text-start bg-light position-sticky start-0">Code</th>
                                @forelse($originalChoiceCodes as $choiceCode)
                                    @php($choiceVisualStatus = $originalChoiceStatus($choiceCode))
                                    <td class="{{ $choiceCellClass($choiceVisualStatus) }}" title="{{ $choiceVisualStatus }}"><code>{{ $choiceCode }}</code></td>
                                @empty
                                    <td>—</td>
                                @endforelse
                            </tr>
                            <tr>
                                <th class="text-start bg-light position-sticky start-0">ABBR</th>
                                @forelse($originalChoiceAbbrs as $choiceIndex => $choiceAbbr)
                                    @php($choiceVisualStatus = $originalChoiceStatus($originalChoiceCodes[$choiceIndex] ?? ''))
                                    <td class="{{ $choiceCellClass($choiceVisualStatus) }}" title="{{ $choiceVisualStatus }}"><code>{{ $choiceAbbr }}</code></td>
                                @empty
                                    <td>—</td>
                                @endforelse
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card-body">
                <h4 class="mb-2">Validated Choice</h4>
                <div class="text-secondary small mb-2">Final validated choice sequence consumed by cadre-wise Merit Generation.</div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered text-center align-middle mb-0 merit-choice-position-table">
                        <tbody>
                            <tr>
                                <th class="text-start bg-light position-sticky start-0">Order</th>
                                @forelse($validatedChoiceCodes as $choiceIndex => $choiceCode)
                                    @php($choiceVisualStatus = $validatedChoiceStatus($choiceCode))
                                    <th class="{{ $choiceCellClass($choiceVisualStatus) }}" title="{{ $choiceVisualStatus }}">{{ $choiceIndex + 1 }}</th>
                                @empty
                                    <td>—</td>
                                @endforelse
                            </tr>
                            <tr>
                                <th class="text-start bg-light position-sticky start-0">Code</th>
                                @forelse($validatedChoiceCodes as $choiceCode)
                                    @php($choiceVisualStatus = $validatedChoiceStatus($choiceCode))
                                    <td class="{{ $choiceCellClass($choiceVisualStatus) }}" title="{{ $choiceVisualStatus }}"><code>{{ $choiceCode }}</code></td>
                                @empty
                                    <td>—</td>
                                @endforelse
                            </tr>
                            <tr>
                                <th class="text-start bg-light position-sticky start-0">ABBR</th>
                                @forelse($validatedChoiceAbbrs as $choiceIndex => $choiceAbbr)
                                    @php($choiceVisualStatus = $validatedChoiceStatus($validatedChoiceCodes[$choiceIndex] ?? ''))
                                    <td class="{{ $choiceCellClass($choiceVisualStatus) }}" title="{{ $choiceVisualStatus }}"><code>{{ $choiceAbbr }}</code></td>
                                @empty
                                    <td>—</td>
                                @endforelse
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">
                <div>
                    <h3 class="card-title">Merit Source Authority</h3>
                    <div class="card-subtitle">Finalized source versions and hashes bound to this Merit processing run.</div>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-vcenter mb-0">
                    <thead><tr><th>Source Module</th><th>Version / Run</th><th>Dataset Hash</th></tr></thead>
                    <tbody>
                        <tr><td><strong>Circular</strong></td><td>v{{ data_get($sourceSnapshot,'circular.version','—') }}</td><td><code class="small text-break user-select-all">{{ data_get($sourceSnapshot,'circular.dataset_hash','—') }}</code></td></tr>
                        <tr><td><strong>Tabulation</strong></td><td>Run #{{ data_get($sourceSnapshot,'tabulation.processing_run_id','—') }} / v{{ data_get($sourceSnapshot,'tabulation.processing_version','—') }}</td><td><code class="small text-break user-select-all">{{ data_get($sourceSnapshot,'tabulation.dataset_hash','—') }}</code></td></tr>
                        <tr><td><strong>Choice Validation</strong></td><td>v{{ data_get($sourceSnapshot,'choice_validation.validation_version','—') }}</td><td><code class="small text-break user-select-all">{{ data_get($sourceSnapshot,'choice_validation.dataset_hash','—') }}</code></td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><h3 class="card-title">Generated Merit Ranking</h3></div>
            <div class="table-responsive"><table class="table table-vcenter mb-0"><tbody>
                <tr><th class="w-40">Common Merit Position</th><td>{{ $result->common_merit_position ?? '—' }}</td></tr>
                <tr><th>General Merit Position</th><td>{{ $result->general_merit_position ?? '—' }}</td></tr>
                <tr><th>Technical Merit Position</th><td>{{ $result->technical_merit_position ?? '—' }}</td></tr>
                <tr><th>all_merit_tech</th><td><code>{{ \App\Models\MeritResult::allMeritTechJson($result->all_merit_tech) }}</code></td></tr>
                <tr><th>Status</th><td>{{ $result->status_reason ?? 'MERIT_RANKED' }}</td></tr>
                <tr><th>Processed At</th><td>{{ $result->processed_at }}</td></tr>
            </tbody></table></div>
        </div>

        <div class="card">
            <div class="card-header"><h3 class="card-title">Cadre-wise Merit Positions</h3></div>
            <div class="table-responsive"><table class="table table-vcenter"><thead><tr><th>Cadre</th><th>Type</th><th>Cadre Merit</th><th>Source Merit</th><th>Choice Position</th><th>Qualification Basis</th></tr></thead><tbody>
                @forelse($result->cadreRanks as $rank)
                    <tr><td>{{ $rank->cadre_code }} ({{ $rank->cadre_abbr }})</td><td>{{ $rank->cadre_type }}</td><td><strong>{{ $rank->cadre_merit_position }}</strong></td><td>{{ $rank->source_merit_position }}</td><td>{{ $rank->choice_position }}</td><td>{{ $rank->qualification_basis }}</td></tr>
                @empty
                    <tr><td colspan="6" class="text-center text-secondary py-4">No cadre-wise merit position generated.</td></tr>
                @endforelse
            </tbody></table></div>
        </div>
    </div>
</div>
@endsection
