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
            font-size: 8.5pt;
            line-height: 1.2;
        }

        /*
         * The mPDF top margin already creates the requested ~0.3 inch
         * separation between the report header and the table.
         */
        .table-gap {
            height: 0;
        }

        table.distribution {
            width: 277mm;
            border-collapse: collapse;
            table-layout: fixed;
            margin: 0 auto;
        }

        thead {
            display: table-header-group;
        }

        tr {
            page-break-inside: avoid;
        }

        th,
        td {
            border: 0.22mm solid {{ $theme->string('colors.border') }};
            padding: 1.4mm 1.0mm;
            vertical-align: middle;
            text-align: center;
        }

        th {
            background: {{ $theme->string('colors.header_background') }};
            font-weight: bold;
            font-size: 8pt;
            white-space: nowrap;
        }

        td.strong {
            font-weight: bold;
        }

        /*
         * A4 landscape usable width with 10mm left/right margins = 277mm.
         * The widths below intentionally total 277mm.
         *
         * REMARKS / SIGNATURE = 55mm each (~2.17 inches).
         * Numeric columns stay compact and close to their header/content width.
         */
        .w-sl         { width: 10mm; }
        .w-mark       { width: 17mm; }
        .w-count      { width: 24mm; }
        .w-small      { width: 10mm; }
        .w-cumulative { width: 29mm; }
        .w-cum-small  { width: 19mm; }
        .w-remarks    { width: 55mm; }
        .w-signature  { width: 55mm; }
    </style>
</head>
<body>
<div class="table-gap"></div>

<table class="distribution">
    <colgroup>
        <col class="w-sl">
        <col class="w-mark">
        <col class="w-count">
        <col class="w-small">
        <col class="w-small">
        <col class="w-small">
        <col class="w-cumulative">
        <col class="w-cum-small">
        <col class="w-cum-small">
        <col class="w-cum-small">
        <col class="w-remarks">
        <col class="w-signature">
    </colgroup>

    <thead>
    <tr>
        <th>SL.</th>
        <th>MARK</th>
        <th>COUNT TOTAL</th>
        <th>GG</th>
        <th>TT</th>
        <th>GT</th>
        <th>CUMULATIVE TOTAL</th>
        <th>CUM. GG</th>
        <th>CUM. TT</th>
        <th>CUM. GT</th>
        <th>REMARKS</th>
        <th>SIGNATURE</th>
    </tr>
    </thead>

    <tbody>
    @forelse ($rows as $row)
        <tr>
            <td>{{ $loop->iteration }}</td>
            <td class="strong">{{ $row['mark'] }}</td>
            <td class="strong">{{ number_format((int) $row['count']['total']) }}</td>
            <td>{{ number_format((int) $row['count']['GG']) }}</td>
            <td>{{ number_format((int) $row['count']['TT']) }}</td>
            <td>{{ number_format((int) $row['count']['GT']) }}</td>
            <td class="strong">{{ number_format((int) $row['cumulative']['total']) }}</td>
            <td>{{ number_format((int) $row['cumulative']['GG']) }}</td>
            <td>{{ number_format((int) $row['cumulative']['TT']) }}</td>
            <td>{{ number_format((int) $row['cumulative']['GT']) }}</td>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
        </tr>
    @empty
        <tr>
            <td colspan="12">No eligible mark rows.</td>
        </tr>
    @endforelse
    </tbody>
</table>
</body>
</html>
