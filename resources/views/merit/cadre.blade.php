@extends('layouts.app')

@section('content')
<div class="page-header">
    <div class="container-xl">
        <div class="row align-items-center">
            <div class="col">
                <h2 class="page-title">{{ $meta->cadre_code }} ({{ $meta->cadre_abbr }}) Merit List</h2>
                <div class="text-secondary">Cadre-wise merit ordered by cadre merit serial.</div>
            </div>
            <div class="col-auto d-flex gap-2">
                @if($state?->status==='finalized'&&!$state?->is_stale&&$state?->latest_run_id===$runId)
                    <a class="btn btn-outline-success" href="{{ route('merit.cadre.export.xlsx',$meta->cadre_code) }}">Export XLSX</a>
                @endif
                <a class="btn btn-outline-secondary" href="{{ route('merit.results',['run'=>$runId]) }}">Back</a>
            </div>
        </div>
    </div>
</div>

<div class="page-body">
    <div class="container-xl">
        <form class="card card-body mb-3" method="GET">
            <input type="hidden" name="run" value="{{ $runId }}">
            <div class="row g-2 align-items-center">
                <div class="col-md-5">
                    <input class="form-control" name="search" value="{{ $search }}" placeholder="Search by Name, REG or USER">
                </div>
                <div class="col-auto"><button class="btn btn-primary">Filter</button></div>
                <div class="col-auto"><a class="btn btn-outline-secondary" href="{{ route('merit.cadre',['cadreCode'=>$meta->cadre_code,'run'=>$runId]) }}">Reset</a></div>
            </div>
        </form>

        <div class="card">
            <div class="card-header">
                <div>
                    <h3 class="card-title">{{ $meta->cadre_code }} ({{ $meta->cadre_abbr }})</h3>
                    <div class="card-subtitle">Candidates: {{ number_format($rows->total()) }}</div>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-vcenter">
                    <thead>
                        <tr>
                            <th>Cadre Merit</th>
                            <th>Candidate</th>
                            <th>Source Merit</th>
                            <th>Choice Position</th>
                            <th>Common</th>
                            <th>General</th>
                            <th>Technical</th>
                            <th>all_merit_tech</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($rows as $r)
                        <tr>
                            <td><strong>{{ $r->cadre_merit_position }}</strong></td>
                            <td><strong>{{ $r->reg }}</strong><br><span>{{ $r->candidate_name ?: '—' }}</span><br><span class="text-secondary small">{{ $r->user_id }}</span></td>
                            <td>{{ $r->source_merit_position }}</td>
                            <td>{{ $r->choice_position }}</td>
                            <td>{{ $r->common_merit_position ?? '—' }}</td>
                            <td>{{ $r->general_merit_position ?? '—' }}</td>
                            <td>{{ $r->technical_merit_position ?? '—' }}</td>
                            <td><code>{{ \App\Models\MeritResult::allMeritTechJson($r->all_merit_tech) }}</code></td>
                            <td>
                                @if(in_array((string) $state?->status,['review_ready','finalized'],true)&&!$state?->is_stale&&$state?->latest_run_id===$runId)
                                    <a class="btn btn-sm btn-outline-primary" href="{{ route('merit.show',$r->merit_result_id) }}">{{ $state?->status==='finalized' ? 'Finalized View' : 'Review View' }}</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-center text-secondary py-4">No candidates match the selected search.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer d-flex justify-content-between align-items-center">
                <div class="text-secondary">Displaying {{ $rows->firstItem() ?? 0 }} to {{ $rows->lastItem() ?? 0 }} of {{ number_format($rows->total()) }}</div>
                <div>{{ $rows->links() }}</div>
            </div>
        </div>
    </div>
</div>
@endsection
