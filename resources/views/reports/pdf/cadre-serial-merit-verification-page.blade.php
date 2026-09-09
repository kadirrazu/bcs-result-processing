<div class="head">
    <div><strong>Exam Name:</strong> {{ $examinationName }}</div>
    <div><strong>Report Title:</strong> {{ $title }}</div>
    <div class="cadre">{{ $section['heading'] }}</div>
    <div class="summary">
        Total Post: {{ number_format($section['total_post']) }}
        &nbsp; | &nbsp;
        Total Allocated: {{ number_format($section['total_allocated']) }}
    </div>
</div>

<table>
    <colgroup>
        @for($groupIndex = 0; $groupIndex < 3; $groupIndex++)
            <col style="width:8%">
            <col style="width:15%">
            <col style="width:10.33%">
        @endfor
    </colgroup>

    <thead>
        <tr>
            @for($groupIndex = 0; $groupIndex < 3; $groupIndex++)
                <th class="{{ $groupIndex > 0 ? 'group-divider' : '' }}">Serial</th>
                <th>{{ $section['merit_label'] }}</th>
                <th>Allocation Basis</th>
            @endfor
        </tr>
    </thead>

    <tbody>
        @if($maxRows === 0)
            <tr>
                <td colspan="9" class="empty">No ACTIVE allocated candidate for this cadre.</td>
            </tr>
        @else
            @for($rowIndex = 0; $rowIndex < $maxRows; $rowIndex++)
                <tr>
                    @for($groupIndex = 0; $groupIndex < 3; $groupIndex++)
                        @php
                            $candidate = $groups->get($groupIndex)?->get($rowIndex);
                        @endphp

                        <td class="{{ $groupIndex > 0 ? 'group-divider' : '' }}">
                            {{ $candidate['serial'] ?? '' }}
                        </td>
                        <td>
                            @if($candidate)
                                <strong>{{ $candidate['merit_position'] ?? '—' }}</strong>
                            @endif
                        </td>
                        <td>
                            @if($candidate)
                                <span class="{{ ($candidate['allocation_basis'] ?? '') === 'MQ' ? 'basis-mq' : 'basis-quota' }}">
                                    {{ $candidate['allocation_basis'] ?? '—' }}
                                </span>
                            @endif
                        </td>
                    @endfor
                </tr>
            @endfor
        @endif
    </tbody>
</table>
