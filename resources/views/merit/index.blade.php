@extends('layouts.app')
@section('content')
<div class="page-header"><div class="container-xl"><div class="row align-items-center"><div class="col"><h2 class="page-title">Merit Generation</h2><div class="text-secondary">Derived ranking from hash-verified Tabulation, Circular and Choice Validation.</div></div></div></div></div>
<div class="page-body"><div class="container-xl">
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<div class="row row-cards mb-3">@foreach($readiness['checks'] as $check)<div class="col-md-4"><div class="card h-100"><div class="card-body"><div class="d-flex justify-content-between"><strong>{{ $check['label'] }}</strong><span class="badge bg-{{ $check['ready']?'success':'danger' }}-lt">{{ $check['ready']?'HASH_VERIFIED':'NOT_READY' }}</span></div><div class="text-secondary small mt-2">{{ $check['detail'] }}</div></div></div></div>@endforeach</div>
<div class="card mb-3"><div class="card-header"><h3 class="card-title">Processing Status Board</h3></div><div class="card-body"><div class="row g-3"><div class="col-md-3"><div class="text-secondary">Current State</div><div class="h3">{{ strtoupper((string)$state->status) }}</div></div><div class="col-md-3"><div class="text-secondary">Latest Version</div><div class="h3">{{ $latestRun?->processing_version ?? '—' }}</div></div><div class="col-md-3"><div class="text-secondary">Common / General / Technical</div><div class="fw-semibold">{{ $latestRun?->common_ranked_count ?? 0 }} / {{ $latestRun?->general_ranked_count ?? 0 }} / {{ $latestRun?->technical_ranked_count ?? 0 }}</div></div><div class="col-md-3"><div class="text-secondary">Cadre Rank Rows</div><div class="h3">{{ $latestRun?->cadre_rank_rows ?? 0 }}</div></div></div>@if($state->is_stale)<div class="alert alert-warning mt-3 mb-0"><strong>STALE</strong> {{ $state->stale_reason }}</div>@endif</div></div>
@php
    $meritGenerationInProgress = $latestRun && in_array(strtolower((string) $latestRun->status), ['queued', 'running'], true);
    $hasGeneratedMerit = (bool) $latestRun;
    $meritGenerateLabel = $hasGeneratedMerit ? 'Regenerate Merit' : 'Generate Merit';
@endphp
<div class="d-flex gap-2 mb-3">
    @if($readiness['ready'])
        @if($meritGenerationInProgress)
            <button class="btn btn-primary" type="button" disabled>Merit Generation in Progress</button>
        @else
            <form method="POST"
                  action="{{ route('merit.generate') }}"
                  @if($hasGeneratedMerit)
                      onsubmit="return confirm('Regenerate Merit? A new Merit processing version will be created. The current finalized/history version will remain preserved.');"
                  @endif>
                @csrf
                <button class="btn btn-primary">{{ $meritGenerateLabel }}</button>
            </form>
        @endif
    @endif

    @if($latestRun)
        <a class="btn btn-outline-secondary" href="{{ route('merit.results',['run'=>$latestRun->id]) }}">Review Results</a>
    @endif
</div>

<div class="card mb-3">
    <div class="card-header">
        <div>
            <h3 class="card-title">Finalization History</h3>
            <div class="card-subtitle">Historical Merit versions can be restored only when current upstream source hashes and the historical Merit dataset hash still match.</div>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-vcenter mb-0">
            <thead><tr><th>Version</th><th>Run</th><th>Status</th><th>Dataset Hash</th><th>Finalized By</th><th>Finalized At</th><th></th></tr></thead>
            <tbody>
            @forelse($finalizationHistory as $finalization)
                @php($finalActor = $auditActors->get($finalization->finalized_by))
                <tr>
                    <td>v{{ $finalization->processing_version }}</td>
                    <td>#{{ $finalization->processing_run_id }}</td>
                    <td><span class="badge bg-{{ $finalization->status === 'current' ? 'success' : 'secondary' }}-lt">{{ strtoupper((string)$finalization->status) }}</span></td>
                    <td style="max-width:320px"><code class="small text-break">{{ $finalization->dataset_hash }}</code></td>
                    <td>@if($finalActor){{ $finalActor->name }}<br><span class="small text-secondary">{{ $finalActor->email }}</span>@else User #{{ $finalization->finalized_by }} @endif</td>
                    <td>{{ $finalization->finalized_at }}</td>
                    <td>
                        @if($finalization->status !== 'current')
                            <form method="POST" action="{{ route('merit.rollback', $finalization) }}" class="d-flex gap-1 align-items-center">
                                @csrf
                                <input class="form-control form-control-sm" style="width:110px" name="confirmation" placeholder="ROLLBACK">
                                <input class="form-control form-control-sm" style="width:180px" name="reason" placeholder="Optional reason">
                                <button class="btn btn-sm btn-outline-warning">Restore</button>
                            </form>
                        @else
                            <span class="text-secondary small">Current</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-secondary py-4">No finalized Merit version yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="card"><div class="card-header"><h3 class="card-title">Recent Audit</h3></div><div class="table-responsive"><table class="table table-vcenter mb-0"><thead><tr><th>Event</th><th>Operator</th><th>Status</th><th>Time</th></tr></thead><tbody>@forelse($audits as $audit)@php($actor=$auditActors->get($audit->actor_id))<tr><td>{{ $audit->event }}</td><td>@if($actor){{ $actor->name }}<br><span class="text-secondary small">{{ $actor->email }}</span>@else System @endif</td><td>{{ strtoupper((string)$audit->to_status) }}</td><td>{{ $audit->created_at }}</td></tr>@empty<tr><td colspan="4" class="text-center text-secondary">No audit events yet.</td></tr>@endforelse</tbody></table></div></div>
</div></div>@endsection
