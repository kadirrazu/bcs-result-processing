@extends('layouts.app')

@section('title', 'Cadre-wise Serial, Merit & Allocation Basis Verification')

@section('page-header')
<div class="row g-2 align-items-center">
    <div class="col">
        <div class="page-pretitle">Cadre Section Reporting · Allocation Verification</div>
        <h2 class="page-title">{{ $title }}</h2>
    </div>

    <div class="col-auto ms-auto d-flex gap-2">
        <form method="POST" action="{{ route('examination-reports.cadre.verification.cadre-serial-merit.pdf') }}">
            @csrf
            <button class="btn btn-primary" type="submit">Export PDF</button>
        </form>

        <button class="btn btn-outline-primary" type="button" onclick="window.print()">Print</button>

        <a class="btn btn-outline-secondary" href="{{ route('examination-reports.cadre.index') }}">
            Back
        </a>
    </div>
</div>
@endsection

@section('content')
<style>
@page {
    size: A4 landscape;
    margin: 0.5in;
}
.csm-page {
    background: #fff;
}
.csm-head {
    margin-bottom: 12px;
}
.csm-title {
    font-weight: 700;
}
.csm-cadre {
    font-size: 1.05rem;
    font-weight: 700;
    margin-top: 4px;
}
.csm-summary {
    margin-top: 5px;
    font-weight: 700;
}
.csm-table {
    width: 100%;
    table-layout: fixed;
}
.csm-table th,
.csm-table td {
    text-align: center;
    vertical-align: middle;
}
.csm-table th {
    font-weight: 700;
}
.csm-group-divider {
    border-left-width: 2px !important;
}
.csm-basis-mq {
    color: #000;
    font-weight: 700;
}
.csm-basis-quota {
    color: #206bc4;
    font-weight: 700;
}
.csm-page-break {
    break-after: page;
    page-break-after: always;
}
@media print {
    .navbar,
    .page-header .btn,
    .footer,
    .no-print {
        display: none !important;
    }

    .page-wrapper {
        margin: 0 !important;
    }

    .container-xl {
        max-width: none !important;
        padding: 0 !important;
    }

    .card {
        border: 0 !important;
        box-shadow: none !important;
    }

    .csm-screen-card {
        margin: 0 !important;
    }

    .csm-screen-card .card-body {
        padding: 0 !important;
    }

    .csm-table th,
    .csm-table td {
        font-size: 9px;
        padding: 4px 3px !important;
    }

    .csm-page {
        break-inside: avoid;
        page-break-inside: avoid;
    }
}
</style>

<div class="alert alert-info py-2 no-print">
    <strong>Confidentiality:</strong>
    Identity-free pre-publication verification report. Only cadre serial, merit position and allocation basis are shown.
</div>

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

        <div class="card csm-screen-card mb-3">
            <div class="card-body csm-page">
                <div class="csm-head">
                    <div><strong>Exam Name:</strong> {{ $examination->name }}</div>
                    <div><strong>Report Title:</strong> {{ $title }}</div>
                    <div class="csm-cadre">{{ $section['heading'] }}</div>
                    <div class="csm-summary">
                        Total Post: {{ number_format($section['total_post']) }}
                        &nbsp; | &nbsp;
                        Total Allocated: {{ number_format($section['total_allocated']) }}
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-sm csm-table mb-0">
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
                                    <th class="{{ $groupIndex > 0 ? 'csm-group-divider' : '' }}">Serial</th>
                                    <th>{{ $section['merit_label'] }}</th>
                                    <th>Allocation Basis</th>
                                @endfor
                            </tr>
                        </thead>

                        <tbody>
                            @if($maxRows === 0)
                                <tr>
                                    <td colspan="9" class="text-secondary py-4">
                                        No ACTIVE allocated candidate for this cadre.
                                    </td>
                                </tr>
                            @else
                                @for($rowIndex = 0; $rowIndex < $maxRows; $rowIndex++)
                                    <tr>
                                        @for($groupIndex = 0; $groupIndex < 3; $groupIndex++)
                                            @php
                                                $candidate = $groups->get($groupIndex)?->get($rowIndex);
                                            @endphp

                                            <td class="{{ $groupIndex > 0 ? 'csm-group-divider' : '' }}">
                                                {{ $candidate['serial'] ?? '' }}
                                            </td>
                                            <td>
                                                @if($candidate)
                                                    <strong>{{ $candidate['merit_position'] ?? '—' }}</strong>
                                                @endif
                                            </td>
                                            <td>
                                                @if($candidate)
                                                    <span class="{{ ($candidate['allocation_basis'] ?? '') === 'MQ' ? 'csm-basis-mq' : 'csm-basis-quota' }}">
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
            </div>
        </div>

        @if($renderedPage < $totalPageCount)
            <div class="csm-page-break"></div>
        @endif
    @endforeach
@endforeach
@endsection
