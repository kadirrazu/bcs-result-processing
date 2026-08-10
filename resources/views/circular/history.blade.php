@extends('layouts.app')
@section('title','Circular Version & Audit History')
@section('content')
<div class="page-header d-print-none"><div class="container-xl"><div class="row align-items-center"><div class="col"><h2 class="page-title">Circular Version & Audit History</h2><div class="text-secondary">Preserved Circular versions and chronological processing/edit audit trail.</div></div><div class="col-auto ms-auto d-flex gap-2"><a class="btn btn-outline-secondary" href="{{ route('circular.index') }}">Workspace</a><a class="btn btn-outline-primary" href="{{ route('circular.view') }}">Current Circular</a></div></div></div></div>
<div class="page-body"><div class="container-xl">
<div class="card mb-4"><div class="card-header"><h3 class="card-title">Version History</h3></div><div class="table-responsive"><table class="table table-vcenter card-table"><thead><tr><th>Version</th><th>State</th><th>Entries</th><th>Active Posts</th><th>Created / Last Changed</th><th class="text-end">Action</th></tr></thead><tbody>
@forelse($versions as $row)
@php($v=(int)$row->version)
<tr><td class="fw-bold">Version {{ $v }}</td><td>
@if($v===(int)$state->current_version)<span class="badge bg-blue-lt text-blue">Current</span>@endif
@if($v===(int)($state->approved_version ?? 0))<span class="badge bg-green-lt text-green">Approved</span>@endif
@if($v===(int)($state->confirmed_version ?? 0))<span class="badge bg-azure-lt text-azure">Confirmed</span>@endif
@if($v===(int)($state->finalized_version ?? 0))<span class="badge bg-purple-lt text-purple">Finalized</span>@endif
@if($v!==(int)$state->current_version && $v!==(int)($state->approved_version ?? 0) && $v!==(int)($state->confirmed_version ?? 0) && $v!==(int)($state->finalized_version ?? 0))<span class="badge bg-secondary-lt text-secondary">Historical</span>@endif
</td><td>{{ number_format((int)$row->entry_count) }}</td><td>{{ number_format((int)$row->active_posts) }}</td><td><div>{{ $row->created_at ? \Illuminate\Support\Carbon::parse($row->created_at)->format('d M Y, h:i A') : '—' }}</div><div class="text-secondary small">Last: {{ $row->updated_at ? \Illuminate\Support\Carbon::parse($row->updated_at)->format('d M Y, h:i A') : '—' }}</div></td><td class="text-end"><a class="btn btn-sm btn-outline-primary" href="{{ route('circular.versions.show',$v) }}">View Snapshot</a></td></tr>
@empty<tr><td colspan="6" class="text-center text-secondary py-4">No Circular version has been created yet.</td></tr>@endforelse
</tbody></table></div></div>

<div class="card"><div class="card-header"><div><h3 class="card-title">Edit / Processing Audit Trail</h3><div class="text-secondary small">Import approval, version forks, manual creation, update and deletion are retained here with actor, reason and before/after snapshots.</div></div></div><div class="table-responsive"><table class="table table-vcenter card-table"><thead><tr><th style="width:170px">Time</th><th>Action</th><th>Version</th><th>Operator</th><th>Reason</th><th>Change Summary</th></tr></thead><tbody>
@forelse($audits as $audit)
@php($summary=is_array($audit->summary)?$audit->summary:[])
<tr><td>{{ $audit->created_at?->format('d M Y, h:i:s A') ?? '—' }}</td><td><span class="fw-semibold">{{ ucwords(str_replace('_',' ',$audit->action)) }}</span></td><td>{{ $summary['version'] ?? ($audit->after_snapshot['version'] ?? $audit->before_snapshot['version'] ?? '—') }}</td><td><div>{{ $audit->actor_name ?: 'System' }}</div>@if($audit->actor_id)<div class="text-secondary small">ID {{ $audit->actor_id }}</div>@endif</td><td>{{ $audit->reason ?: '—' }}</td><td>
@if($audit->action==='circular_entry_updated')<span class="badge bg-yellow-lt text-yellow">Entry updated</span>
@elseif($audit->action==='circular_entry_created')<span class="badge bg-green-lt text-green">Entry created</span>
@elseif($audit->action==='circular_entry_deleted')<span class="badge bg-red-lt text-red">Entry deleted</span>
@elseif($audit->action==='circular_import_approved')<span class="badge bg-blue-lt text-blue">Import approved</span>
@elseif($audit->action==='circular_version_forked_for_edit')<span class="badge bg-azure-lt text-azure">Version forked</span>
@else<span class="badge bg-secondary-lt text-secondary">{{ $audit->action }}</span>@endif
@if(!empty($summary['batch_id']))<div class="text-secondary small mt-1">Batch #{{ $summary['batch_id'] }}@if(isset($summary['rows'])) · {{ number_format((int)$summary['rows']) }} rows @endif</div>@endif
<details class="mt-1"><summary class="small text-primary" style="cursor:pointer">Before / After</summary><div class="row g-2 mt-1"><div class="col-md-6"><div class="text-secondary small">Before</div><pre class="small mb-0 text-wrap">{{ json_encode($audit->before_snapshot, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?: '—' }}</pre></div><div class="col-md-6"><div class="text-secondary small">After</div><pre class="small mb-0 text-wrap">{{ json_encode($audit->after_snapshot, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?: '—' }}</pre></div></div></details>
</td></tr>
@empty<tr><td colspan="6" class="text-center text-secondary py-4">No audit records yet.</td></tr>@endforelse
</tbody></table></div>@if($audits->hasPages())<div class="card-footer">{{ $audits->links() }}</div>@endif</div>
</div></div>
@endsection
