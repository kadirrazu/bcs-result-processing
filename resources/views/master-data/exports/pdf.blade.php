<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $definition->label() }}</title>
    <style>
        @font-face {
            font-family: "{{ $banglaFont['family'] }}";
            font-style: normal;
            font-weight: normal;
            src: url("{{ $banglaFont['data_uri'] }}") format("truetype");
        }

        @page {
            margin: 0.5in;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            color: #182433;
            font-family: "DejaVu Sans", sans-serif;
            font-size: 13pt;
            line-height: 1.25;
        }

        .page-header {
            position: fixed;
            top: -0.42in;
            right: 0;
            left: 0;
            height: 0.36in;
            text-align: center;
        }

        .page-header h1 {
            margin: 0;
            font-size: 15pt;
            line-height: 1.2;
        }

        .page-header .generated {
            margin-top: 2px;
            color: #68778a;
            font-size: 9pt;
        }

        .page-footer {
            position: fixed;
            right: 0;
            bottom: -0.42in;
            left: 0;
            height: 0.24in;
            border-top: 0.5px solid #d7dde6;
            color: #a0aaba;
            font-size: 8pt;
            line-height: 0.24in;
            text-align: center;
        }

        table {
            width: 100%;
            margin-top: 0.14in;
            border-collapse: collapse;
            table-layout: auto;
        }

        thead { display: table-header-group; }
        tfoot { display: table-footer-group; }

        th,
        td {
            border: 1px solid #788497;
            padding: 5px 6px;
            vertical-align: top;
            overflow-wrap: break-word;
        }

        th {
            background: #e9eef5;
            font-size: 13pt;
            font-weight: bold;
            text-align: left;
            white-space: normal;
        }

        tr { page-break-inside: avoid; }

        .bangla {
            font-family: "{{ $banglaFont['family'] }}", sans-serif;
            font-size: 13pt;
            line-height: 1.35;
        }

        .center { text-align: center; }
        .nowrap { white-space: nowrap; }

        /* Sensible content-based widths; title/name columns receive most available space. */
        .w-sl { width: 5%; }
        .w-code { width: 10%; }
        .w-abbr { width: 11%; }
        .w-title-en { width: 25%; }
        .w-title-bn { width: 29%; }
        .w-type { width: 7%; }
        .w-order { width: 7%; }
        .w-status { width: 8%; }
        .w-subject-code { width: 18%; }
        .w-subject-name { width: 64%; }
        .w-subject-status { width: 18%; }
    </style>
</head>
<body>
    <header class="page-header">
        <h1>{{ $definition->label() }}</h1>
        <div class="generated">Generated at: {{ $generatedAt->format('d M Y, h:i:s A') }}</div>
    </header>

    <footer class="page-footer">
        {{ $definition->label() }} &nbsp;|&nbsp; Generated at {{ $generatedAt->format('d M Y, h:i:s A') }}
    </footer>

    <table>
        @if($definition->key() === 'cadre-masters')
            <colgroup>
                <col class="w-sl">
                <col class="w-code">
                <col class="w-abbr">
                <col class="w-title-en">
                <col class="w-title-bn">
                <col class="w-type">
                <col class="w-order">
                <col class="w-status">
            </colgroup>
        @else
            <colgroup>
                <col class="w-sl">
                <col class="w-subject-code">
                <col class="w-subject-name">
                <col class="w-subject-status">
            </colgroup>
        @endif

        <thead>
            <tr>
                <th class="center">SL</th>
                @foreach($definition->columns() as $attribute => $label)
                    <th @class([
                        'center' => in_array($attribute, ['cadre_type', 'display_order', 'is_active'], true),
                    ])>{{ $label }}</th>
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
                <tr>
                    <td colspan="{{ count($definition->columns()) + 1 }}" class="center">No records found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
