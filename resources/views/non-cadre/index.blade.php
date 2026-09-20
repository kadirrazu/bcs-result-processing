@extends('layouts.app')
@section('title', 'Non Cadre Processing')

@section('page-header')
<div class="row g-2 align-items-center">
    <div class="col">
        <div class="page-pretitle">{{ $examination->name }}</div>
        <h2 class="page-title">Non Cadre Processing</h2>
    </div>
</div>
@endsection

@section('content')
@if(!($gate['ready'] ?? false))
<div class="alert alert-warning">
    <strong>Non Cadre Processing is currently inactive.</strong>
    {{ $gate['reason'] ?? 'Finalize the current Cadre Allocation before starting Non-Cadre Processing.' }}
</div>
@else
<div class="alert alert-success">
    <strong>Cadre Allocation authority is current.</strong>
    Non-Cadre processing may proceed using Cadre data as a read-only upstream feed.
</div>
@endif

<div class="card mb-3">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4"><div class="subheader">Module</div><div class="fw-semibold">Single isolated Non-Cadre workflow</div></div>
            <div class="col-md-4"><div class="subheader">Database</div><div class="fw-semibold">Current examination database</div></div>
            <div class="col-md-4"><div class="subheader">Cadre dependency</div><div class="fw-semibold">Read-only finalized/current Allocation authority</div></div>
        </div>
    </div>
</div>

<div class="row row-cards">
@foreach($stages as $stage)
    @php
        $upstreamReady = (bool) ($gate['ready'] ?? false);
        $status = strtoupper(str_replace('_', ' ', (string) $stage['status']));
    @endphp
    <div class="col-md-6 col-xl-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start gap-2">
                    <div>
                        <div class="subheader">{{ $stage['code'] }}</div>
                        <h3 class="card-title mt-2">{{ $stage['title'] }}</h3>
                    </div>
                    <span class="badge {{ $stage['status'] === 'finalized' ? 'bg-green-lt' : ($stage['status'] === 'stale' ? 'bg-red-lt' : 'bg-secondary-lt') }}">{{ $status }}</span>
                </div>
                <p class="text-secondary mb-0">
                    @switch($stage['key'])
                        @case('circular') Import, validate, version and finalize Non-Cadre posts by post code. @break
                        @case('seat_breakup') Generate and finalize MQ/CFF/EM/PHC seat breakup from the effective circular. @break
                        @case('choice') Preserve original choices, validate post codes/subjects, review removals and manage audited adjustments. @break
                        @case('allocation') Allocate by Common Merit Position and effective choice, including quota and special-requirement review. @break
                        @default Publish reports only from the finalized/current Non-Cadre allocation authority.
                    @endswitch
                </p>
            </div>
            <div class="card-footer">
                @if($stage['key'] === 'circular' && $upstreamReady)
                    <a href="{{ route('non-cadre.circular.index') }}" class="btn btn-outline-primary w-100">Open NC1 — Circular</a>
                @elseif($stage['key'] === 'seat_breakup' && $upstreamReady && $effectiveCircular)
                    <a href="{{ route('non-cadre.seat-breakup.index') }}" class="btn btn-outline-primary w-100">Open NC2 — Seat Breakup</a>
                @elseif($stage['key'] === 'choice' && $upstreamReady && $effectiveCircular && $effectiveSeatBreakup)
                    <a href="{{ route('non-cadre.choice.index') }}" class="btn btn-outline-primary w-100">Open NC3 — Choice Validation & Adjustment</a>
                @elseif($stage['key'] === 'allocation' && $upstreamReady && $effectiveCircular && $effectiveSeatBreakup && $effectiveChoice)
                    <a href="{{ route('non-cadre.allocation.index') }}" class="btn btn-outline-primary w-100">Open NC4 — Non-Cadre Allocation</a>
                @else
                    <button type="button" class="btn btn-outline-primary w-100" disabled>{{ $upstreamReady ? 'Available after previous stage is finalized' : 'Unavailable until Cadre Allocation is current' }}</button>
                @endif
            </div>
        </div>
    </div>
@endforeach
</div>
@endsection
