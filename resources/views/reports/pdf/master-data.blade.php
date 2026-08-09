<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            color: {{ $theme->string('colors.text') }};
            font-family: {{ $theme->string('fonts.english_family') }}, sans-serif;
            font-size: {{ $theme->number('fonts.english_size_pt') }}pt;
            line-height: {{ $theme->number('table.body_line_height') }};
        }
        table.master-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        th, td {
            border: {{ $theme->number('table.border_width_mm') }}mm solid {{ $theme->string('colors.border') }};
            padding: {{ $theme->number('table.cell_padding_vertical_mm') }}mm {{ $theme->number('table.cell_padding_horizontal_mm') }}mm;
            vertical-align: top;
            overflow-wrap: break-word;
        }
        th {
            background: {{ $theme->string('colors.header_background') }};
            font-size: {{ $theme->number('fonts.table_header_size_pt') }}pt;
            font-weight: bold;
            line-height: {{ $theme->number('table.header_line_height') }};
            text-align: left;
        }
        .bangla {
            font-family: '{{ $banglaFontFamily }}', sans-serif;
            font-size: {{ $theme->number('fonts.bangla_size_pt') }}pt;
            line-height: {{ $theme->number('table.bangla_line_height') }};
        }
        .center { text-align: center; }
        .nowrap { white-space: nowrap; }
        .w-sl { width: 4%; }
        .w-code { width: 7%; }
        .w-abbr { width: 10%; }
        .w-title-en { width: 25%; }
        .w-title-bn { width: 29%; }
        .w-type { width: 6%; }
        .w-order { width: 11%; }
        .w-status { width: 8%; }
        .w-subject-code { width: 18%; }
        .w-subject-name { width: 64%; }
        .w-subject-status { width: 18%; }
    </style>
</head>
<body>
<table class="master-table">
    @if($definition->key() === 'cadre-masters')
        <colgroup>
            <col class="w-sl"><col class="w-code"><col class="w-abbr"><col class="w-title-en">
            <col class="w-title-bn"><col class="w-type"><col class="w-order"><col class="w-status">
        </colgroup>
    @else
        <colgroup>
            <col class="w-sl"><col class="w-subject-code"><col class="w-subject-name"><col class="w-subject-status">
        </colgroup>
    @endif
    <thead>
    <tr>
        <th class="center">SL</th>
        @foreach($definition->columns() as $attribute => $label)
            <th @class(['center' => in_array($attribute, ['cadre_type', 'display_order', 'is_active'], true)])>{{ $label }}</th>
        @endforeach
    </tr>
    </thead>
    <tbody>
    @forelse($records as $record)
        <tr>
            <td class="center nowrap">{{ $loop->iteration }}</td>
            @foreach($definition->columns() as $attribute => $label)
                @php
                    $value = $record->getAttribute($attribute);
                    $printable = $attribute === 'is_active'
                        ? ($value ? 'Active' : 'Inactive')
                        : ($value instanceof \BackedEnum ? $value->value : $value);
                    $containsBangla = is_string($printable)
                        && preg_match('/[\x{0980}-\x{09FF}]/u', $printable) === 1;
                @endphp
                <td @class([
                    'bangla' => in_array($attribute, ['cadre_name_bn', 'post_name_bn'], true) || $containsBangla,
                    'center' => in_array($attribute, ['cadre_type', 'display_order', 'is_active'], true),
                    'nowrap' => in_array($attribute, ['cadre_code', 'cadre_abbr', 'subject_code', 'cadre_type'], true),
                ])>{{ $printable }}</td>
            @endforeach
        </tr>
    @empty
        <tr><td colspan="{{ count($definition->columns()) + 1 }}" class="center">No records found.</td></tr>
    @endforelse
    </tbody>
</table>
</body>
</html>
