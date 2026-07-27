@extends('layouts.app')

@section('title', 'Examinations')

@section('content')
<div class="page-header d-print-none">
    <div class="container-xl">
        <div class="row g-2 align-items-center">
            <div class="col">
                <div class="page-pretitle">Administration</div>
                <h2 class="page-title">Examinations</h2>
            </div>
            <div class="col-auto ms-auto">
                <a href="{{ route('examinations.create') }}" class="btn btn-primary">Add Examination</a>
            </div>
        </div>
    </div>
</div>

<div class="page-body"><div class="container-xl"><div class="card">
    <div class="card-header">
        <form method="GET" action="{{ route('examinations.index') }}" class="row g-2 w-100">
            <div class="col-md-6"><input type="search" name="search" value="{{ $search }}" class="form-control" placeholder="Search BCS, slug or database"></div>
            <div class="col-auto"><button class="btn btn-outline-primary">Search</button></div>
            @if ($search !== '')<div class="col-auto"><a href="{{ route('examinations.index') }}" class="btn btn-outline-secondary">Clear</a></div>@endif
        </form>
    </div>
    <div class="table-responsive"><table class="table table-vcenter card-table">
        <thead><tr><th>Examination</th><th>Database</th><th>Health</th><th>Status</th><th>Selection</th><th class="w-1"></th></tr></thead>
        <tbody>
        @forelse ($examinations as $examination)
            <tr>
                <td><div class="fw-semibold">{{ $examination->name }}</div><div class="text-secondary small">{{ $examination->slug }}</div></td>
                <td><code>{{ $examination->database_name }}</code></td>
                <td>
                    @if ($examination->database_health_status === 'connected')
                        <span class="badge bg-success-lt">Connected</span>
                    @elseif ($examination->database_health_status === 'failed')
                        <span class="badge bg-danger-lt">Failed</span>
                    @else
                        <span class="badge bg-secondary-lt">Not checked</span>
                    @endif
                    @if ($examination->database_checked_at)
                        <div class="text-secondary small mt-1">{{ $examination->database_checked_at->diffForHumans() }}</div>
                    @endif
                </td>
                <td><span class="badge bg-blue-lt">{{ $examination->status->label() }}</span></td>
                <td>
                    @if (app(\App\Support\Examinations\ExaminationContext::class)->is($examination))
                        <span class="badge bg-success-lt">Active context</span>
                    @elseif ($examination->isSelectable())
                        <form method="POST" action="{{ route('examinations.select', $examination) }}">@csrf<button class="btn btn-sm btn-outline-success">Select</button></form>
                    @else
                        <span class="badge bg-secondary-lt">Unavailable</span>
                    @endif
                </td>
                <td>
                    <div class="btn-list flex-nowrap">
                        <form method="POST" action="{{ route('examinations.check-database', $examination) }}">@csrf<button class="btn btn-sm btn-outline-secondary">Check DB</button></form>
                        <a href="{{ route('examinations.edit', $examination) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                    </div>
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="text-center text-secondary py-4">No examinations found.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    @if ($examinations->hasPages())<div class="card-footer">{{ $examinations->links() }}</div>@endif
</div></div></div>
@endsection
