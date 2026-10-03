@extends('layouts.app') @section('content')
<div class="container-xl"><div class="page-header"><div class="row align-items-center"><div class="col"><h2 class="page-title">Punishment Screening Report #{{$run->id}}</h2><div class="text-secondary">{{strtoupper($run->phase)}} · Reference {{$run->reference_date?->format('Y-m-d')}} · Algorithm {{$run->matching_algorithm}}</div></div><div class="col-auto"><a class="btn btn-outline-secondary" href="{{route('historical-punishments.screening.index',['phase'=>$run->phase])}}">Back</a> @if($run->status==='completed')<a class="btn btn-outline-success" href="{{route('historical-punishments.screening.export',$run)}}">XLSX Active Warnings</a>@endif</div></div></div>
@if(session('success'))<div class="alert alert-success">{{session('success')}}</div>@endif
<div class="alert alert-info">Warning/report only. No candidate status, result, merit or allocation is changed by this report.</div>
@if(in_array($run->status,['queued','running'],true))
<div class="card mb-3" id="screening-progress-card" data-status-url="{{ route('historical-punishments.screening.status', $run) }}">
    <div class="card-body">
        <div class="d-flex justify-content-between">
            <strong id="screening-progress-status">{{strtoupper($run->status)}} — background processing</strong>
            <span id="screening-progress-percent">{{$run->progressPercent()}}%</span>
        </div>
        <div class="progress mt-2">
            <div id="screening-progress-bar" class="progress-bar" style="width: {{$run->progressPercent()}}%"></div>
        </div>
        <div class="text-secondary small mt-2" id="screening-progress-count">Processed {{number_format($run->processed_count ?? 0)}} of {{number_format($run->candidate_count)}} candidates.</div>
        <div class="text-secondary small mt-1">Progress updates automatically. This report will refresh when processing finishes.</div>
    </div>
</div>

<script>
(() => {
    const card = document.getElementById('screening-progress-card');
    if (!card) return;

    const statusUrl = card.dataset.statusUrl;
    const statusEl = document.getElementById('screening-progress-status');
    const percentEl = document.getElementById('screening-progress-percent');
    const barEl = document.getElementById('screening-progress-bar');
    const countEl = document.getElementById('screening-progress-count');
    let stopped = false;

    const formatNumber = value => new Intl.NumberFormat().format(Number(value || 0));

    const poll = async () => {
        if (stopped || document.hidden) return;

        try {
            const response = await fetch(statusUrl, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                cache: 'no-store',
            });
            if (!response.ok) throw new Error(`HTTP ${response.status}`);

            const data = await response.json();
            const percent = Math.max(0, Math.min(100, Number(data.progress_percent || 0)));
            const status = String(data.status || 'queued').toUpperCase();

            statusEl.textContent = `${status} — background processing`;
            percentEl.textContent = `${percent}%`;
            barEl.style.width = `${percent}%`;
            countEl.textContent = `Processed ${formatNumber(data.processed_count)} of ${formatNumber(data.candidate_count)} candidates.`;

            if (data.status === 'completed' || data.status === 'failed') {
                stopped = true;
                window.location.reload();
            }
        } catch (error) {
            // A temporary polling/network failure must not interrupt the queued job.
        }
    };

    const timer = window.setInterval(poll, 1500);
    poll();

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden && !stopped) poll();
    });

    window.addEventListener('beforeunload', () => window.clearInterval(timer), { once: true });
})();
</script>
@endif
@if($run->status==='failed')<div class="alert alert-danger"><strong>Screening failed.</strong> {{$run->failure_message}}</div>@endif
<div class="row row-cards mb-3">@foreach(['Population'=>$run->candidate_count,'Processed'=>$run->processed_count ?? 0,'Matched'=>$run->matched_count,'Needs Review'=>$run->review_count,'Active Warnings'=>$run->active_warning_count] as $l=>$v)<div class="col-6 col-md"><div class="card"><div class="card-body text-center"><div class="text-secondary">{{$l}}</div><div class="h2 mb-0">{{$v}}</div></div></div></div>@endforeach</div>
<div class="card"><div class="table-responsive"><table class="table table-bordered table-vcenter"><thead><tr><th>Candidate</th><th>Historical Record</th><th>Match</th><th>Punishment</th><th>Reason</th><th>Review</th></tr></thead><tbody>@forelse($matches as $m) @php($h=$hist[$m->historical_punishment_id]??null)<tr><td>{{$m->reg}}<br><strong>{{$m->name}}</strong><br><span class="text-secondary">{{$m->user_id}}</span></td><td>BCS {{$h?->bcs}} · REG {{$h?->reg}}<br>{{$h?->name}}</td><td><span class="badge {{$m->match_status==='matched'?'bg-green-lt':($m->match_status==='review'?'bg-yellow-lt':'bg-secondary-lt')}}">{{strtoupper($m->match_status)}}</span><br><small>{{$m->match_method}}</small></td><td>@if($h?->is_lifetime)<strong>LIFETIME</strong>@else {{$h?->punishment_start?->format('Y-m-d')}} → {{$h?->punishment_end?->format('Y-m-d')}} @endif<br><span class="badge {{$m->punishment_active?'bg-red-lt':'bg-secondary-lt'}}">{{$m->punishment_active?'ACTIVE AT REFERENCE DATE':'EXPIRED/INACTIVE'}}</span></td><td>{{$h?->punishment_reason}}</td><td>@if($run->status==='completed' && $m->match_status==='review')<form method="POST" action="{{route('historical-punishments.screening.review',$m)}}">@csrf<select name="decision" class="form-select form-select-sm mb-1"><option value="matched">Confirm Match</option><option value="rejected">Reject</option></select><input name="review_note" class="form-control form-control-sm mb-1" placeholder="Mandatory reason" required><button class="btn btn-sm btn-primary">Save</button></form>@else {{$m->review_note}} @endif</td></tr>@empty<tr><td colspan="6" class="text-center text-secondary">{{ in_array($run->status,['queued','running'],true) ? 'Screening is still processing.' : 'No identity matches found.' }}</td></tr>@endforelse</tbody></table></div><div class="card-footer">{{$matches->links()}}</div></div></div>
@endsection
