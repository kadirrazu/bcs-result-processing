@php
    $booklet = (bool) ($booklet ?? false);
    $showHigherChoice = !$booklet;
    $columnCount = $booklet ? 10 : 10;
@endphp
<table class="avr-table {{ $booklet ? 'avr-booklet-table' : '' }}">
    <thead>
    <tr>
        <th class="avr-sl-col">Sl.</th>
        @if($booklet)
            <th class="avr-candidate-heading">Candidate<br>Information</th>
        @endif
        <th class="avr-merit-heading">
            @foreach(($meritHeadingLines ?? ['MERIT', 'POSITION']) as $headingLine)
                <span>{{ $headingLine }}</span>@if(!$loop->last)<br>@endif
            @endforeach
        </th>
        <th>Category &amp;<br>Written Track</th>
        <th>Allocated<br>Cadre</th>
        <th>Merit Details</th>
        <th>Quota</th>
        <th>Bachelor Subject &amp;<br>PRS</th>
        <th>Choice List</th>
        @if($showHigherChoice)
            <th>Higher Choice<br>Missed Reason</th>
        @endif
        <th>{{ $reportType === 'quota' ? 'Outcome / Remarks' : 'Remarks' }}</th>
    </tr>
    </thead>
    <tbody>
    @forelse($rows as $row)
        @php
            $categoryClass = in_array(strtolower((string)$row['category']), ['gg','gt','tt','t'], true)
                ? 'avr-code-'.strtolower((string)$row['category'])
                : 'avr-code-default';
            $trackClass = in_array(strtolower((string)$row['written_track']), ['gg','gt','tt','t'], true)
                ? 'avr-code-'.strtolower((string)$row['written_track'])
                : 'avr-code-default';
        @endphp
        <tr
            @if($interactive ?? false)
                class="allocation-report-row"
                data-merit="{{ $row['merit_position'] ?? '' }}"
                data-cadre="{{ strtoupper((string)($row['allocation_abbr'] ?? '')) }}"
                data-reg="{{ $booklet ? strtoupper((string)($row['candidate_reg'] ?? '')) : '' }}"
                data-name="{{ $booklet ? strtoupper((string)($row['candidate_name'] ?? '')) : '' }}"
                data-quotas="{{ implode(',', $row['quota_labels']) }}"
                data-outcome="{{ $row['allocation_outcome'] }}"
            @endif
        >
            <td class="avr-center avr-sl-col">{{ $loop->iteration }}</td>
            @if($booklet)
                <td class="avr-candidate-cell">
                    <div><span class="avr-candidate-label">Reg:</span> <strong>{{ $row['candidate_reg'] ?: '—' }}</strong></div>
                    <div><span class="avr-candidate-label">Name:</span> {{ $row['candidate_name'] ?: '—' }}</div>
                    <div><span class="avr-candidate-label">Father:</span> {{ $row['candidate_father'] ?: '—' }}</div>
                    <div><span class="avr-candidate-label">DOB:</span> {{ $row['candidate_dob'] ?: '—' }}</div>
                </td>
            @endif
            <td class="avr-center"><strong>{{ $row['merit_position'] ?? '—' }}</strong></td>
            <td class="avr-middle-left">
                <div>CAT: <span class="{{ $categoryClass }}">{{ $row['category'] ?: '—' }}</span></div>
                <div class="avr-cell-separator"></div>
                <div>TRACK: <span class="{{ $trackClass }}">{{ $row['written_track'] ?: '—' }}</span></div>
            </td>
            <td class="avr-center avr-allocation-cell">
                @if(!empty($row['allocation_abbr']))
                    <span class="avr-allocated-cadre">{{ $row['allocation_abbr'] }}</span>@if($row['is_withheld'] ?? false) <span class="avr-withheld">(WITHHELD)</span>@endif<br>
                    <span>(Serial: {{ $row['allocation_serial'] }})</span><br>
                    <span class="{{ ($row['allocation_basis'] ?? '') === 'MQ' ? 'avr-basis-mq' : 'avr-basis-quota' }}">{{ $row['allocation_basis'] }}</span>
                @else
                    —
                @endif
            </td>
            <td>
                @if($row['merit_general'] !== null)
                    General: <span class="avr-merit-value">{{ $row['merit_general'] }}</span>
                @endif
                @if($row['merit_technical'] !== null)
                    @if($row['merit_general'] !== null)<div class="avr-merit-separator"></div>@endif
                    Technical: <span class="avr-merit-value">{{ $row['merit_technical'] }}</span>
                @endif
                @if(!empty($row['technical_cadre_merits']))
                    @if($row['merit_general'] !== null || $row['merit_technical'] !== null)<div class="avr-merit-separator"></div>@endif
                    @foreach(collect($row['technical_cadre_merits'])->chunk(2) as $meritLine)
                        <div class="avr-merit-line">@foreach($meritLine as $item)<span class="avr-merit-item">{{ $item['abbr'] }}: <span class="avr-merit-value">{{ $item['position'] }}</span></span>@if(!$loop->last) · @endif @endforeach</div>
                    @endforeach
                @endif
                @if($row['merit_general'] === null && $row['merit_technical'] === null && empty($row['technical_cadre_merits']))—@endif
            </td>
            <td class="avr-center">
                @if(!empty($row['quota_labels']))
                    @foreach($row['quota_labels'] as $quota)<span class="avr-quota">{{ $quota }}</span>@if(!$loop->last)<br>@endif @endforeach
                @else
                    <span class="avr-non-quota">Non Quota</span>
                @endif
            </td>
            <td>
                <div><span class="avr-subject-label">B_SUBJECT:</span>
                    <span class="avr-subject-value">@if(!empty($row['bachelor_code'])){{ $row['bachelor_code'] }} — {{ $row['bachelor_name'] }}@else—@endif</span>
                </div>
                <div class="avr-cell-separator"></div>
                <div><span class="avr-subject-label">PRS:</span>
                    <span class="avr-subject-value">@if(!empty($row['prs_code'])){{ $row['prs_code'] }} — {{ $row['prs_name'] }}@else—@endif</span>
                </div>
            </td>
            <td>
                @if(!empty($row['historical_cutoff']))
                    <div class="avr-choice-label">Validated Choice</div>
                    @forelse($row['validated_choices']->chunk(5) as $choiceLine)
                        <div class="avr-choice-line">@foreach($choiceLine as $choice)<span class="avr-choice">{{ $choice['abbr'] }} ({{ $choice['code'] }})</span>@endforeach</div>
                    @empty
                        —
                    @endforelse
                    <div class="avr-choice-separator"></div>
                @endif
                <div class="avr-choice-label">Allocation-ready Choice</div>
                @forelse($row['choices']->chunk(5) as $choiceLine)
                    <div class="avr-choice-line">@foreach($choiceLine as $choice)<span class="avr-choice {{ $choice['allocated'] ? 'avr-choice-allocated' : '' }}">{{ $choice['abbr'] }} ({{ $choice['code'] }})</span>@endforeach</div>
                @empty
                    —
                @endforelse
                @if(!empty($row['historical_cutoff']))
                    <div class="avr-choice-separator"></div>
                    <div>Historical Cut-off due to <span class="avr-review-cadre">{{ $row['historical_cutoff']['cadre'] }}</span> in <strong>{{ $row['historical_cutoff']['bcs'] }}</strong></div>
                @endif
            </td>
            @if($showHigherChoice)
                <td class="{{ empty($row['higher_choice_missed_reasons']) ? 'avr-center' : '' }}">
                    @forelse($row['higher_choice_missed_reasons'] as $review)
                        <div class="avr-missed-line"><span class="avr-review-cadre">{{ $review['cadre'] }}</span> - Last Merit (@foreach($review['last_merits'] as $basis => $lastMerit)<span class="{{ $basis === 'MQ' ? 'avr-last-merit-basis' : 'avr-last-merit-quota' }}">{{ $basis }}: <span class="avr-review-value">{{ $lastMerit ?? '—' }}</span></span>@if(!$loop->last) · @endif @endforeach)</div>
                    @empty
                        —
                    @endforelse
                </td>
            @endif
            <td class="{{ empty($row['historical_allocations']) && empty($row['remarks']) ? 'avr-center' : '' }}">
                @if(!empty($row['remarks']))
                    <div>{{ $row['remarks'] }}</div>
                    @if(!empty($row['historical_allocations']))<div class="avr-cell-separator"></div>@endif
                @endif
                @forelse($row['historical_allocations'] as $historyRow)
                    <div class="avr-history-line"><span>{{ $historyRow['bcs'] }}:</span> @foreach($historyRow['cadres'] as $historyCadre)<span class="avr-history-cadre">{{ $historyCadre['cadre'] }}</span> <span class="avr-history-source">({{ implode(', ', $historyCadre['sources']) }})</span>@if(!$loop->last) / @endif @endforeach</div>
                @empty
                    @if(empty($row['remarks']))—@endif
                @endforelse
            </td>
        </tr>
    @empty
        <tr><td colspan="{{ $columnCount }}" class="avr-center avr-empty">No candidates found for this finalized report population.</td></tr>
    @endforelse
    @if($interactive ?? false)
        <tr id="allocation-report-no-match" class="d-none"><td colspan="{{ $columnCount }}" class="avr-center avr-empty">No candidate matches the current filters.</td></tr>
    @endif
    </tbody>
</table>
