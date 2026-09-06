@extends('layouts.app')
@section('content')
<div class="page-header d-print-none">
    <div class="container-xl">
        <div class="row g-2 align-items-center">
            <div class="col">
                <h2 class="page-title">A6 — Short Allocation Summary</h2>
                <div class="text-secondary">Concise final allocation overview in Circular category/serial order. Detailed quota/NM movement remains available in the In-depth Summary.</div>
            </div>
            <div class="col-auto ms-auto d-flex gap-2 flex-wrap">
                <a class="btn btn-outline-primary" href="{{ route('allocation.a6.summary') }}">Open In-depth Summary</a>
                <a class="btn btn-outline-secondary" href="{{ route('allocation.a6.index') }}">Back to A6 — Reporting &amp; Export</a>
            </div>
        </div>
    </div>
</div>

<div class="page-body"><div class="container-xl">
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

    <div class="row row-cards mb-3">
        <div class="col-sm-4">
            <div class="card h-100"><div class="card-body">
                <div class="text-secondary">Total Sanctioned Posts</div>
                <div class="h2 mb-0">{{ number_format($totals['total_post']) }}</div>
            </div></div>
        </div>
        <div class="col-sm-4">
            <div class="card h-100"><div class="card-body">
                <div class="text-secondary">Total Allocated</div>
                <div class="h2 mb-0">{{ number_format($totals['total_allocated']) }}</div>
            </div></div>
        </div>
        <div class="col-sm-4">
            <div class="card h-100"><div class="card-body">
                <div class="text-secondary">Total Vacant</div>
                <div class="h2 mb-0">{{ number_format($totals['total_vacant']) }}</div>
            </div></div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">
            <div>
                <h3 class="card-title">Short Allocation Summary</h3>
                <div class="card-subtitle">Overall sanctioned, allocated and vacant posts only; read-only from the same finalized A5-bound A4 source.</div>
            </div>
            <div class="ms-auto d-flex gap-2 flex-wrap">
                <form method="POST" action="{{ route('allocation.a6.summary.short.export') }}">@csrf
                    <input type="hidden" name="format" value="xlsx">
                    <button class="btn btn-success">Queue Excel</button>
                </form>
                <form method="POST" action="{{ route('allocation.a6.summary.short.export') }}">@csrf
                    <input type="hidden" name="format" value="pdf">
                    <button class="btn btn-danger">Queue PDF</button>
                </form>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-vcenter table-bordered table-sm mb-0">
                <thead><tr class="text-center align-middle">
                    <th>Category</th><th>SL</th><th>Code / Abbr</th>
                    <th class="text-start">Cadre Name</th><th class="text-start">Post Name</th>
                    <th>Total Post</th><th>Total Allocated</th><th>Withheld</th><th>Cancelled</th><th>Published Active</th><th>Total Vacant</th>
                </tr></thead>
                <tbody>
                @php($lastCategory = null)
                @foreach($rows as $row)
                    <tr class="{{ $lastCategory !== null && $lastCategory !== $row['category'] ? 'border-top border-2' : '' }}">
                        <td class="text-center">{{ $row['category'] }}</td>
                        <td class="text-center">{{ $row['serial_label'] }}</td>
                        <td class="text-center"><strong>{{ $row['cadre_code'] }}</strong> / {{ $row['cadre_abbr'] }}</td>
                        <td>{{ $row['cadre_name'] ?: '—' }}</td>
                        <td>{{ $row['post_name'] ?: '—' }}</td>
                        <td class="text-end">{{ number_format($row['total_post']) }}</td>
                        <td class="text-end">{{ number_format($row['total_allocated']) }}</td>
                        <td class="text-end">{{ number_format($row['withheld_count']) }}</td>
                        <td class="text-end">{{ number_format($row['cancelled_count']) }}</td>
                        <td class="text-end fw-bold">{{ number_format($row['published_active']) }}</td>
                        <td class="text-end">{{ number_format($row['total_vacant']) }}</td>
                    </tr>
                    @php($lastCategory = $row['category'])
                @endforeach
                </tbody>
                <tfoot><tr class="fw-bold">
                    <td colspan="5" class="text-end">TOTAL</td>
                    <td class="text-end">{{ number_format($totals['total_post']) }}</td>
                    <td class="text-end">{{ number_format($totals['total_allocated']) }}</td>
                    <td class="text-end">{{ number_format($totals['withheld_count']) }}</td>
                    <td class="text-end">{{ number_format($totals['cancelled_count']) }}</td>
                    <td class="text-end">{{ number_format($totals['published_active']) }}</td>
                    <td class="text-end">{{ number_format($totals['total_vacant']) }}</td>
                </tr></tfoot>
            </table>
        </div>
    </div>
</div></div>
@endsection
