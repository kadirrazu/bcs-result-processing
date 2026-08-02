@extends('layouts.app')
@section('title','Written Import Review')
@section('content')
<div class="page-header"><div class="container-xl"><div class="row align-items-center">
<div class="col"><h2 class="page-title">Written Import Batch #{{ $record->id }}</h2><div class="text-secondary">{{ $record->original_name }} · <span id="batch-status">{{ str_replace('_',' ',$record->status) }}</span></div></div>
<div class="col-auto d-flex gap-2"><a href="{{ route('written.index') }}" class="btn btn-outline-secondary">Back</a><a href="{{ route('written.import.report',$record) }}" class="btn btn-outline-secondary">Download Issue CSV</a></div>
</div></div></div>
<div class="page-body"><div class="container-xl">
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

<div class="card mb-3"><div class="card-body"><div class="d-flex justify-content-between mb-2"><strong>Current Phase Progress</strong><span id="progress-label">{{ number_format((float)$record->progress_percent,2) }}%</span></div><div class="progress progress-lg"><div id="progress-bar" class="progress-bar" style="width:{{ min(100,(float)$record->progress_percent) }}%"></div></div><div class="text-secondary mt-2"><span id="processed-count">{{ number_format($record->processed_rows) }}</span> processed · <span id="total-count">{{ number_format($record->total_rows) }}</span> rows</div><div id="failure-message" class="alert alert-danger mt-3 mb-0 {{ $record->failure_message ? '' : 'd-none' }}">{{ $record->failure_message }}</div></div></div>

<div class="row row-cards mb-3">@foreach(['total_rows'=>'Source','staged_rows'=>'Staged','valid_rows'=>'Valid','warning_rows'=>'Warnings','invalid_rows'=>'Invalid','identity_conflict_rows'=>'Identity conflict','approved_rows'=>'Approved'] as $field=>$label)<div class="col-sm-6 col-lg"><div class="card card-sm"><div class="card-body"><div class="text-secondary">{{ $label }}</div><div class="h2 mb-0" id="metric-{{ $field }}">{{ number_format($record->$field) }}</div></div></div></div>@endforeach</div>

@if($record->status === 'staged')
<div class="alert alert-info d-flex align-items-center justify-content-between"><div>Fast staging is complete. Run identity, eligibility, mark, PRS and review validation.</div><form method="post" action="{{ route('written.import.validate',$record) }}">@csrf<button class="btn btn-primary">Validate Staged Data</button></form></div>
@elseif($record->status === 'validated')
@if((int)$record->approved_rows === 0)
<div class="alert alert-warning"><div class="d-flex align-items-center justify-content-between gap-2 flex-wrap"><div><strong>{{ number_format($record->valid_rows + $record->warning_rows) }}</strong> rows are ready to merge. Rows with warnings can still be merged; invalid rows will remain in this batch for correction.</div><div class="d-flex gap-2"><form method="post" action="{{ route('written.import.validate',$record) }}">@csrf<button class="btn btn-outline-primary">Check Again</button></form><form method="post" action="{{ route('written.import.approve',$record) }}" onsubmit="return confirm('Merge all valid and warning Written rows?');">@csrf<button class="btn btn-success">Approve &amp; Merge</button></form></div></div></div>
@else
<div class="alert alert-info">The corrected rows have been checked. The system will merge only the corrected rows that are now valid; previously approved rows will not be touched.</div>
@endif
@elseif($record->status === 'failed' && (int)$record->approved_rows === 0)
<div class="alert alert-danger d-flex align-items-center justify-content-between gap-2 flex-wrap"><div>@if((int)$record->staged_rows === 0)Staging failed before any rows became available for validation. Retry staging after applying the header fix.@elseThe last queue phase failed after staging. Existing staged rows can be revalidated.@endif</div><div class="d-flex gap-2">@if((int)$record->staged_rows === 0)<form method="post" action="{{ route('written.import.retry-staging',$record) }}">@csrf<button class="btn btn-danger">Retry Staging</button></form>@else<form method="post" action="{{ route('written.import.validate',$record) }}">@csrf<button class="btn btn-outline-danger">Retry Validation</button></form>@endif @if(((int)$record->valid_rows+(int)$record->warning_rows)>0)<form method="post" action="{{ route('written.import.approve',$record) }}">@csrf<button class="btn btn-danger">Retry Approve &amp; Merge</button></form>@endif</div></div>
@elseif($record->status === 'approved')<div class="alert alert-success">Written snapshot approved: {{ number_format($record->inserted_rows) }} inserted, {{ number_format($record->updated_rows) }} updated. <a href="{{ route('written.results') }}">Open merged Written results</a>.</div>@endif


@if(((int)$record->invalid_rows + (int)$record->identity_conflict_rows) > 0 && in_array((string)$record->status,['validated','failed','approved'],true))
<div class="card mb-3 border-warning"><div class="card-header"><h3 class="card-title">Correct invalid rows</h3></div><div class="card-body">
<p class="text-secondary">Download only the rows that still need correction. Keep <strong>source_row</strong> unchanged, fix those rows in Excel, then upload the same workbook here. Valid rows and warning-only rows are protected and cannot be changed through this upload. @if((int)$record->approved_rows > 0) This batch has already been approved, so only the corrected rows that pass validation will be added; existing approved rows will remain unchanged. @endif</p>
@error('correction_file')<div class="alert alert-danger">{{ $message }}</div>@enderror
<div class="d-flex gap-2 flex-wrap align-items-end"><a class="btn btn-outline-warning" href="{{ route('written.import.corrections.template',$record) }}">Download Invalid Rows</a><form method="post" action="{{ route('written.import.corrections.store',$record) }}" enctype="multipart/form-data" class="d-flex gap-2 flex-wrap align-items-end">@csrf<div><label class="form-label">Corrected workbook</label><input class="form-control" type="file" name="correction_file" accept=".xlsx,.csv" required></div><button class="btn btn-warning" onclick="return confirm('Apply these corrections to the invalid Written rows and run validation again?');">Upload Corrections</button></form></div>
</div></div>
@endif


@if(isset($corrections) && $corrections->isNotEmpty())
<div class="card mb-3"><div class="card-header"><h3 class="card-title">Recent correction uploads</h3></div><div class="table-responsive"><table class="table table-vcenter"><thead><tr><th>Source row</th><th>Previous validation</th><th>File</th><th>Corrected by</th><th>Time</th></tr></thead><tbody>
@foreach($corrections as $correction)<tr><td>{{ $correction->source_row }}</td><td>{{ str_replace('_',' ',(string)$correction->validation_status_before) }}</td><td>{{ $correction->source_filename }}</td><td>{{ $correction->actor_name ?? ('User #'.$correction->actor_id) }}</td><td>{{ $correction->created_at?->format('d-m-Y h:i:s A') }}</td></tr>@endforeach
</tbody></table></div></div>
@endif

<div class="card mb-3"><div class="card-header"><h3 class="card-title">Validation / Review Filters</h3></div><div class="card-body"><form method="get" class="row g-2">
<div class="col-md-2"><label class="form-label">Validation</label><select class="form-select" name="validation"><option value="all">All</option>@foreach(['warning'=>'Warning','invalid'=>'Invalid','identity_conflict'=>'Identity Conflict','valid'=>'Valid'] as $v=>$label)<option value="{{ $v }}" @selected($filters['validation']===$v)>{{ $label }}</option>@endforeach</select></div>
<div class="col-md-3"><label class="form-label">High-mark review</label><select class="form-select" name="high_mark"><option value="">All marks</option><option value="any" @selected($filters['highMark']==='any')>Any subject ≥ configured threshold</option>@foreach($highMarkSubjects as $code)<option value="{{ $code }}" @selected($filters['highMark']===$code)>{{ $code === '008_009' ? '008 + 009 combined' : $code }}</option>@endforeach</select></div>
<div class="col-md-3"><label class="form-label">REG / USER</label><input class="form-control" name="search" value="{{ $filters['search'] }}"></div><div class="col-md-auto align-self-end"><button class="btn btn-primary">Filter</button></div><div class="col-md-auto align-self-end"><a class="btn btn-outline-secondary" href="{{ route('written.import.result',$record) }}">Reset</a></div>
</form></div></div>

<div class="card"><div class="card-header"><h3 class="card-title">Staged Rows — warnings first</h3></div><div class="table-responsive"><table class="table table-vcenter"><thead><tr><th>Row</th><th>REG</th><th>USER</th><th>PRS</th><th>Source note</th><th>Validation</th><th>Messages</th></tr></thead><tbody>
@forelse($rows as $row)
@php($vs=$row->validation_status instanceof \BackedEnum ? $row->validation_status->value : (string)$row->validation_status)
<tr class="{{ $vs === 'warning' ? 'table-warning' : (in_array($vs,['invalid','identity_conflict'],true) ? 'table-danger' : '') }}"><td>{{ $row->source_row }}</td><td>{{ $row->reg }}</td><td>{{ $row->user_id }}</td><td>{{ $row->prs_code ?? '—' }}</td><td>{{ $row->data_source_note ?? '—' }}</td><td><strong>{{ str_replace('_',' ',$vs) }}</strong></td><td>{{ implode(' | ',array_merge($row->validation_errors ?? [],$row->validation_warnings ?? [])) }}</td></tr>
@empty<tr><td colspan="7" class="text-center text-secondary py-4">No rows match the selected filters.</td></tr>@endforelse
</tbody></table></div>@if($rows->hasPages())<div class="card-footer">{{ $rows->links() }}</div>@endif</div>
</div></div>
@endsection
@push('scripts')
@if(in_array($record->status,['queued','staging','validation_queued','validating','approval_queued','approving'],true))
<script>(()=>{const url=@json(route('written.import.status',$record));const fmt=v=>new Intl.NumberFormat().format(v??0);const keys=['total_rows','staged_rows','valid_rows','warning_rows','invalid_rows','identity_conflict_rows','approved_rows'];const refresh=async()=>{try{const r=await fetch(url,{headers:{Accept:'application/json'}});if(!r.ok)return setTimeout(refresh,4000);const d=await r.json();document.getElementById('batch-status').textContent=d.status.replaceAll('_',' ');document.getElementById('progress-label').textContent=Number(d.progress_percent).toFixed(2)+'%';document.getElementById('progress-bar').style.width=Math.min(100,Number(d.progress_percent))+'%';document.getElementById('processed-count').textContent=fmt(d.processed_rows);document.getElementById('total-count').textContent=fmt(d.total_rows);for(const k of keys){const e=document.getElementById('metric-'+k);if(e)e.textContent=fmt(d[k]);}if(d.failure_message){const e=document.getElementById('failure-message');e.textContent=d.failure_message;e.classList.remove('d-none');}if(d.finished)setTimeout(()=>location.reload(),700);else setTimeout(refresh,1500);}catch(_){setTimeout(refresh,5000);}};setTimeout(refresh,1000);})();</script>
@endif
@endpush
