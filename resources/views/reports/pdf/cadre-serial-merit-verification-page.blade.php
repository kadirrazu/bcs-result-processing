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
            <col style="width:6.5%">
            <col style="width:15.5%">
            <col style="width:10.4167%">
            @if($groupIndex < 2)<col style="width:1.375%">@endif
        @endfor
    </colgroup>

    <thead>
        <tr>
            @for($groupIndex = 0; $groupIndex < 3; $groupIndex++)
                <th>Serial</th>
                <th>
                    @php
                        $meritHeader = (string) $section['merit_label'];
                        $meritPrefix = str_ends_with($meritHeader, ' Merit Position')
                            ? substr($meritHeader, 0, -strlen(' Merit Position'))
                            : null;
                    @endphp
                    @if($meritPrefix !== null)
                        {{ $meritPrefix }} Merit<br>Position
                    @else
                        {{ $meritHeader }}
                    @endif
                </th>
                <th>Allocation<br>Basis</th>
                @if($groupIndex < 2)<th class="gap"></th>@endif
            @endfor
        </tr>
    </thead>

    <tbody>
        @if($maxRows === 0)
            <tr>
                <td colspan="11" class="empty">No ACTIVE allocated candidate for this cadre.</td>
            </tr>
        @else
            @for($rowIndex = 0; $rowIndex < $maxRows; $rowIndex++)
                <tr>
                    @for($groupIndex = 0; $groupIndex < 3; $groupIndex++)
                        @php
                            $candidate = $groups->get($groupIndex)?->get($rowIndex);
                        @endphp

                        <td>{{ $candidate['serial'] ?? '' }}</td>
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
                        @if($groupIndex < 2)<td class="gap"></td>@endif
                    @endfor
                </tr>
            @endfor
        @endif
    </tbody>
</table>
