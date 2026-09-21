@extends('layouts.app')
@section('title','NC5 — Non-Cadre Reporting')
@section('page-header')
<div class="row g-2 align-items-center">
    <div class="col">
        <div class="page-pretitle">Non Cadre Processing</div>
        <h2 class="page-title">NC5 — Non-Cadre Reporting</h2>
    </div>
    <div class="col-auto"><a class="btn btn-outline-secondary" href="{{ route('non-cadre.index') }}">Back to Non Cadre Processing</a></div>
</div>
@endsection
@section('content')
@if(!$gate['ready'])
    <div class="alert alert-warning"><strong>Reporting is disabled.</strong> {{ $gate['reason'] }}</div>
@else
    <div class="alert alert-success py-2"><strong>Source:</strong> Finalized NC4 Run #{{ $gate['run']->version }} · {{ number_format($gate['run']->total_allocated) }} allocated</div>
@endif

{{-- Locked first row: Interactive 6 + TXT 3 + DOCX 3 --}}
<div class="row row-cards mb-3">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header"><h3 class="card-title">Interactive Reporting</h3></div>
            <div class="card-body">
                <div class="fw-bold mb-2">Allocation Verification Reports</div>
                <div class="btn-list mb-4">
                    <a class="btn btn-outline-primary {{ $gate['ready']?'':'disabled' }}" href="{{ $gate['ready']?route('non-cadre.reporting.common','verification'):'#' }}">Common Merit Position Report</a>
                    <a class="btn btn-outline-primary {{ $gate['ready']?'':'disabled' }}" href="{{ $gate['ready']?route('non-cadre.reporting.posts','verification'):'#' }}">Post Choice &amp; Quota Eligibility</a>
                    <a class="btn btn-outline-primary {{ $gate['ready']?'':'disabled' }}" href="{{ $gate['ready']?route('non-cadre.reporting.serial-merit'):'#' }}">Post-wise Serial, Common Merit &amp; Allocation Basis</a>
                </div>
                <div class="fw-bold mb-2">Booklet Publishing Reports</div>
                <div class="btn-list">
                    <a class="btn btn-outline-success {{ $gate['ready']?'':'disabled' }}" href="{{ $gate['ready']?route('non-cadre.reporting.common','booklet'):'#' }}">Common Merit Position Report</a>
                    <a class="btn btn-outline-success {{ $gate['ready']?'':'disabled' }}" href="{{ $gate['ready']?route('non-cadre.reporting.posts','booklet'):'#' }}">Post Choice &amp; Quota Eligibility</a>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-header"><h3 class="card-title">TXT Export</h3></div>
            <div class="card-body">
                <form method="POST" action="{{ route('non-cadre.reporting.exports.txt') }}">
                    @csrf
                    <label class="form-label">Registrations per Line</label>
                    <input class="form-control mb-2" type="number" name="registrations_per_line" min="1" max="20" value="8">
                    <label class="form-label">Report Title</label>
                    <input class="form-control mb-3" name="report_title" value="Final Non-Cadre Allocation">
                    <button class="btn btn-primary" @disabled(!$gate['ready'])>Queue TXT Export</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-header"><h3 class="card-title">DOCX Publishing</h3></div>
            <div class="card-body d-flex flex-column">
                <p class="text-secondary">Fill a Word template using Non-Cadre post placeholders and finalized allocation totals.</p>
                <a class="btn btn-primary mt-auto {{ $gate['ready']?'':'disabled' }}" href="{{ $gate['ready']?route('non-cadre.reporting.docx'):'#' }}">Fill DOCX Template</a>
            </div>
        </div>
    </div>
</div>

{{-- Candidate exports intentionally start on their own row. --}}
<div class="row row-cards mb-3">
    <div class="col-12">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Candidate Data Export</h3></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="border rounded p-3 h-100">
                            <div class="fw-bold mb-2">Non-Cadre Allocation Eligible Candidates</div>
                            <div class="text-secondary mb-3">All candidates in the finalized NC4 frozen allocation-eligible population, including allocated and unallocated candidates.</div>
                            <div class="btn-list">
                                <form method="POST" action="{{ route('non-cadre.reporting.exports.candidates',['scope'=>'eligible','format'=>'xlsx']) }}">@csrf<button class="btn btn-outline-success" @disabled(!$gate['ready'])>Export XLSX</button></form>
                                <form method="POST" action="{{ route('non-cadre.reporting.exports.candidates',['scope'=>'eligible','format'=>'dbf']) }}">@csrf<button class="btn btn-outline-primary" @disabled(!$gate['ready'])>Export DBF</button></form>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="border rounded p-3 h-100">
                            <div class="fw-bold mb-2">Non-Cadre Allocated Candidates</div>
                            <div class="text-secondary mb-3">Only candidates who received a final Non-Cadre post allocation in the current finalized NC4 run.</div>
                            <div class="btn-list">
                                <form method="POST" action="{{ route('non-cadre.reporting.exports.candidates',['scope'=>'allocated','format'=>'xlsx']) }}">@csrf<button class="btn btn-outline-success" @disabled(!$gate['ready'])>Export XLSX</button></form>
                                <form method="POST" action="{{ route('non-cadre.reporting.exports.candidates',['scope'=>'allocated','format'=>'dbf']) }}">@csrf<button class="btn btn-outline-primary" @disabled(!$gate['ready'])>Export DBF</button></form>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mt-3 small text-secondary"><strong>Fields:</strong> user, reg, name, cff, em, phc, allocation_ready_choice, common_merit_position, allocation_status, allocated_post_code, allocation_basis</div>
            </div>
        </div>
    </div>
</div>

@if($exports->isNotEmpty())
<div class="card">
    <div class="card-header"><h3 class="card-title">Recent Export Jobs <span class="text-secondary fw-normal">(Latest 10)</span></h3></div>
    <div class="table-responsive">
        <table class="table table-bordered table-vcenter table-sm mb-0" style="font-size:.78rem">
            <thead><tr><th>Queued</th><th>Type</th><th>Scope</th><th>Operator</th><th>Status</th><th>Progress</th><th class="w-1">Action</th></tr></thead>
            <tbody>
            @foreach($exports as $x)
                @php($operator = $x->generated_by ? $operatorUsers->get((int) $x->generated_by) : null)
                <tr>
                    <td>{{ $x->queued_at?->format('d-m-Y h:i A') }}</td>
                    <td>{{ $x->export_type }}</td>
                    <td>{{ $x->scope ?: '—' }}</td>
                    <td>{{ $x->generated_by ? $x->generated_by.' - '.($operator?->name ?? 'Unknown User') : '—' }}</td>
                    <td><span class="badge bg-{{ $x->status==='completed'?'success':($x->status==='failed'?'danger':'azure') }}-lt">{{ strtoupper($x->status) }}</span></td>
                    <td>{{ (int) $x->progress_percent }}%</td>
                    <td><a class="btn btn-sm btn-outline-primary" href="{{ route('non-cadre.reporting.exports.show',$x) }}">Open</a></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif
@endsection
