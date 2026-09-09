<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
body {
    font-family: DejaVu Sans, sans-serif;
    font-size: 8pt;
    color: #182433;
}
.page {
    page-break-after: always;
}
.page.last {
    page-break-after: auto;
}
.head {
    margin-bottom: 4mm;
    line-height: 1.35;
}
.title {
    font-weight: 700;
}
.cadre {
    font-size: 10pt;
    font-weight: 700;
    margin-top: 1mm;
}
.summary {
    margin-top: 1mm;
    font-weight: 700;
}
table {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
}
th,
td {
    border: 0.2mm solid #aeb8c2;
    padding: 1.2mm 0.8mm;
    text-align: center;
    vertical-align: middle;
}
th {
    background: #f3f5f7;
    font-weight: 700;
}
.group-divider {
    border-left: 0.5mm solid #6c7a89;
}
.basis-mq {
    color: #000;
    font-weight: 700;
}
.basis-quota {
    color: #206bc4;
    font-weight: 700;
}
.empty {
    color: #667085;
    padding: 5mm 1mm;
}
</style>
</head>
<body>

@php
    $totalPageCount = $sections->sum(fn ($section) => $section['pages']->count());
    $renderedPage = 0;
@endphp

@foreach($sections as $section)
    @foreach($section['pages'] as $pageIndex => $page)
        @php
            $renderedPage++;
            $groups = $page['groups'];
            $maxRows = collect($groups)->map(fn ($group) => $group->count())->max() ?? 0;
        @endphp

        <div class="page {{ $renderedPage === $totalPageCount ? 'last' : '' }}">
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
        </div>
    @endforeach
@endforeach

</body>
</html>
