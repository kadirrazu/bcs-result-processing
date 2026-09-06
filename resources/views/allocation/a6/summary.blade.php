@extends('layouts.app')
@section('content')
<div class="page-header d-print-none">
    <div class="container-xl">
        <div class="row g-2 align-items-center">
            <div class="col">
                <h2 class="page-title">A6 — In-depth Allocation Summary</h2>
                <div class="text-secondary">Final seat utilization summary in Circular category/serial order from the current finalized A5-bound A4 seat ledger.</div>
            </div>
            <div class="col-auto ms-auto">
                <a class="btn btn-outline-secondary" href="{{ route('allocation.a6.index') }}">Back to A6 — Reporting &amp; Export</a>
            </div>
        </div>
    </div>
</div>

<div class="page-body"><div class="container-xl">
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

    <div class="row row-cards mb-3">
        <div class="col-sm-6 col-lg-3">
            <div class="card h-100"><div class="card-body">
                <div class="text-secondary">Total Sanctioned Posts</div>
                <div class="h2 mb-0">{{ number_format($totals['total_post']) }}</div>
            </div></div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card h-100"><div class="card-body">
                <div class="text-secondary">Total Allocated</div>
                <div class="h2 mb-0">{{ number_format($totals['total_allocated']) }}</div>
            </div></div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card h-100"><div class="card-body">
                <div class="text-secondary">Total Vacant</div>
                <div class="h2 mb-0">{{ number_format($totals['total_vacant']) }}</div>
            </div></div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card h-100"><div class="card-body">
                <div class="text-secondary">Quota Posts Converted to NM</div>
                <div class="h2 mb-0">{{ number_format($totals['converted_in']) }}</div>
            </div></div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">
            <div>
                <h3 class="card-title">In-depth Allocation Seat Summary</h3>
                <div class="card-subtitle">Read-only reporting projection. “Rest” means quota capacity neither occupied under that quota nor converted to NM; Merit Rest means unused final merit/NM capacity.</div>
            </div>
            <div class="ms-auto d-flex gap-2 flex-wrap">
                <a class="btn btn-outline-primary" href="{{ route('allocation.a6.summary.short') }}">Open Short Summary</a>
                <form method="POST" action="{{ route('allocation.a6.summary.export') }}">@csrf
                    <input type="hidden" name="format" value="xlsx">
                    <button class="btn btn-success">Queue Excel</button>
                </form>
                <form method="POST" action="{{ route('allocation.a6.summary.export') }}">@csrf
                    <input type="hidden" name="format" value="pdf">
                    <button class="btn btn-danger">Queue PDF</button>
                </form>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-vcenter table-bordered table-sm mb-0">
                <thead>
                    <tr class="text-center align-middle">
                        <th rowspan="2">Category</th>
                        <th rowspan="2">SL</th>
                        <th rowspan="2">Code / Abbr</th>
                        <th rowspan="2" class="text-start">Cadre Name</th>
                        <th rowspan="2" class="text-start">Post Name</th>
                        <th colspan="6">Overall</th>
                        <th colspan="5">Merit Pool</th>
                        <th colspan="4">CFF</th>
                        <th colspan="4">EM</th>
                        <th colspan="4">PHC</th>
                        <th colspan="3">Phase-2 Movement</th>
                    </tr>
                    <tr class="text-center align-middle">
                        <th>Post</th><th>Allocated</th><th>Withheld</th><th>Cancelled</th><th>Published Active</th><th>Vacant</th>
                        <th>Original MQ</th><th>NM Converted In</th><th>Capacity</th><th>Allocated</th><th>Rest</th>
                        <th>Post</th><th>Allocated</th><th>NM Converted</th><th>Rest</th>
                        <th>Post</th><th>Allocated</th><th>NM Converted</th><th>Rest</th>
                        <th>Post</th><th>Allocated</th><th>NM Converted</th><th>Rest</th>
                        <th>NM Allocations</th><th>Shifted</th><th>Quota to Merit</th>
                    </tr>
                </thead>
                <tbody>
                @php($lastCategory = null)
                @foreach($rows as $row)
                    <tr class="{{ $lastCategory !== null && $lastCategory !== $row['category'] ? 'border-top border-2' : '' }}">
                        <td class="text-center">{{ $row['category'] }}</td>
                        <td class="text-center">{{ $row['serial_label'] }}</td>
                        <td class="text-center"><strong>{{ $row['cadre_code'] }}</strong> / {{ $row['cadre_abbr'] }}</td>
                        <td class="text-start">{{ $row['cadre_name'] ?: '—' }}</td>
                        <td class="text-start">{{ $row['post_name'] ?: '—' }}</td>
                        <td class="text-end">{{ number_format($row['total_post']) }}</td>
                        <td class="text-end">{{ number_format($row['total_allocated']) }}</td>
                        <td class="text-end">{{ number_format($row['withheld_count']) }}</td>
                        <td class="text-end">{{ number_format($row['cancelled_count']) }}</td>
                        <td class="text-end fw-bold">{{ number_format($row['published_active']) }}</td>
                        <td class="text-end">{{ number_format($row['total_vacant']) }}</td>
                        <td class="text-end">{{ number_format($row['mq_post']) }}</td>
                        <td class="text-end">{{ number_format($row['converted_in']) }}</td>
                        <td class="text-end">{{ number_format($row['merit_capacity']) }}</td>
                        <td class="text-end">{{ number_format($row['merit_allocated']) }}</td>
                        <td class="text-end">{{ number_format($row['merit_rest']) }}</td>
                        <td class="text-end">{{ number_format($row['cff_post']) }}</td>
                        <td class="text-end">{{ number_format($row['cff_allocated']) }}</td>
                        <td class="text-end">{{ number_format($row['cff_converted']) }}</td>
                        <td class="text-end">{{ number_format($row['cff_rest']) }}</td>
                        <td class="text-end">{{ number_format($row['em_post']) }}</td>
                        <td class="text-end">{{ number_format($row['em_allocated']) }}</td>
                        <td class="text-end">{{ number_format($row['em_converted']) }}</td>
                        <td class="text-end">{{ number_format($row['em_rest']) }}</td>
                        <td class="text-end">{{ number_format($row['phc_post']) }}</td>
                        <td class="text-end">{{ number_format($row['phc_allocated']) }}</td>
                        <td class="text-end">{{ number_format($row['phc_converted']) }}</td>
                        <td class="text-end">{{ number_format($row['phc_rest']) }}</td>
                        <td class="text-end">{{ number_format($row['nm_allocations']) }}</td>
                        <td class="text-end">{{ number_format($row['shifted_allocations']) }}</td>
                        <td class="text-end">{{ number_format($row['quota_to_merit']) }}</td>
                    </tr>
                    @php($lastCategory = $row['category'])
                @endforeach
                </tbody>
                <tfoot>
                    <tr class="fw-bold">
                        <td colspan="5" class="text-end">TOTAL</td>
                        <td class="text-end">{{ number_format($totals['total_post']) }}</td>
                        <td class="text-end">{{ number_format($totals['total_allocated']) }}</td>
                        <td class="text-end">{{ number_format($totals['withheld_count']) }}</td>
                        <td class="text-end">{{ number_format($totals['cancelled_count']) }}</td>
                        <td class="text-end">{{ number_format($totals['published_active']) }}</td>
                        <td class="text-end">{{ number_format($totals['total_vacant']) }}</td>
                        <td class="text-end">{{ number_format($totals['mq_post']) }}</td>
                        <td class="text-end">{{ number_format($totals['converted_in']) }}</td>
                        <td class="text-end">{{ number_format($totals['merit_capacity']) }}</td>
                        <td class="text-end">{{ number_format($totals['merit_allocated']) }}</td>
                        <td class="text-end">{{ number_format($totals['merit_rest']) }}</td>
                        <td class="text-end">{{ number_format($totals['cff_post']) }}</td>
                        <td class="text-end">{{ number_format($totals['cff_allocated']) }}</td>
                        <td class="text-end">{{ number_format($totals['cff_converted']) }}</td>
                        <td class="text-end">{{ number_format($totals['cff_rest']) }}</td>
                        <td class="text-end">{{ number_format($totals['em_post']) }}</td>
                        <td class="text-end">{{ number_format($totals['em_allocated']) }}</td>
                        <td class="text-end">{{ number_format($totals['em_converted']) }}</td>
                        <td class="text-end">{{ number_format($totals['em_rest']) }}</td>
                        <td class="text-end">{{ number_format($totals['phc_post']) }}</td>
                        <td class="text-end">{{ number_format($totals['phc_allocated']) }}</td>
                        <td class="text-end">{{ number_format($totals['phc_converted']) }}</td>
                        <td class="text-end">{{ number_format($totals['phc_rest']) }}</td>
                        <td class="text-end">{{ number_format($totals['nm_allocations']) }}</td>
                        <td class="text-end">{{ number_format($totals['shifted_allocations']) }}</td>
                        <td class="text-end">{{ number_format($totals['quota_to_merit']) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div></div>
@endsection
