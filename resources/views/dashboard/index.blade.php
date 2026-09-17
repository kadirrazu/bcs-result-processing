@extends('layouts.app')

@section('title', 'Dashboard')

@section('page-header')
    <div class="row g-2 align-items-center">
        <div class="col">
            <div class="page-pretitle">BCS Result Processing System</div>
            <h2 class="page-title">Global Dashboard</h2>
        </div>
    </div>
@endsection

@section('content')
    <div class="row row-deck row-cards">
        <div class="col-sm-6 col-lg-3">
            <div class="card"><div class="card-body">
                <div class="subheader">System</div>
                <div class="h3 m-0">BCS Result Processing</div>
                <div class="text-secondary mt-1">Central administration</div>
            </div></div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card"><div class="card-body">
                <div class="subheader">Application</div>
                <div class="h3 m-0">Laravel {{ app()->version() }}</div>
                <div class="text-secondary mt-1 d-flex align-items-center gap-2 flex-wrap">
                    <span>Environment:</span>
                    @php($environment = app()->environment())
                    <span class="badge {{ $environment === 'production' ? 'bg-green-lt text-green' : ($environment === 'local' ? 'bg-yellow-lt text-yellow' : 'bg-azure-lt text-azure') }}">
                        {{ ucfirst($environment) }}
                    </span>
                </div>
            </div></div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card"><div class="card-body">
                <div class="subheader">Authentication</div>
                <div class="h3 m-0 text-success">Active</div>
                <div class="text-secondary mt-1">Authenticated operator workspace</div>
            </div></div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card"><div class="card-body">
                <div class="subheader">Data Architecture</div>
                <div class="h3 m-0">Exam Isolated</div>
                <div class="text-secondary mt-1">Central masters + per-exam processing</div>
            </div></div>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-header">
            <div>
                <h3 class="card-title">Global System Workspace</h3>
                <div class="card-subtitle">Manage examinations, reusable master data and system administration from one place.</div>
            </div>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6 col-xl-3">
                    <a class="card card-link card-link-pop h-100" href="{{ route('examinations.index') }}">
                        <div class="card-body"><div class="fw-semibold">Examinations</div><div class="text-secondary mt-1">Create, configure, select and manage BCS examination workspaces.</div></div>
                    </a>
                </div>
                <div class="col-md-6 col-xl-3">
                    <a class="card card-link card-link-pop h-100" href="{{ route('cadre-masters.index') }}">
                        <div class="card-body"><div class="fw-semibold">Master Data</div><div class="text-secondary mt-1">Maintain reusable cadre, subject, registration and related master authorities.</div></div>
                    </a>
                </div>
                <div class="col-md-6 col-xl-3">
                    <a class="card card-link card-link-pop h-100" href="{{ route('previous-bcs-repository.index') }}">
                        <div class="card-body"><div class="fw-semibold">Previous BCS Repository</div><div class="text-secondary mt-1">Manage reusable historical BCS recommendation datasets.</div></div>
                    </a>
                </div>
                <div class="col-md-6 col-xl-3">
                    <a class="card card-link card-link-pop h-100" href="{{ route('users.index') }}">
                        <div class="card-body"><div class="fw-semibold">Users &amp; Access</div><div class="text-secondary mt-1">Maintain system users and administrative access.</div></div>
                    </a>
                </div>
            </div>
            <div class="mt-4">
                <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                    <div>
                        <div class="fw-semibold">System &amp; Examination Status Guide</div>
                        <div class="text-secondary small">Use the global dashboard for system administration and the selected examination workspace for result-processing status.</div>
                    </div>
                </div>

                <div class="table-responsive border rounded">
                    <table class="table table-vcenter mb-0">
                        <thead>
                            <tr>
                                <th style="width: 22%">Scope</th>
                                <th style="width: 28%">Where to manage / view</th>
                                <th>What it means</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="fw-semibold">Global System</td>
                                <td>This Dashboard</td>
                                <td class="text-secondary">Use the cards above to manage examinations, reusable master data, previous BCS datasets, users and other system-wide administration.</td>
                            </tr>
                            <tr>
                                <td class="fw-semibold">Selected Examination</td>
                                <td><a href="{{ route('examination-overview.index') }}" class="fw-semibold">Processing Overview</a></td>
                                <td class="text-secondary">Shows the selected examination's module readiness and processing status, including Not Started, Ready, Finalized and Stale/Outdated states.</td>
                            </tr>
                            <tr>
                                <td class="fw-semibold">New Examination</td>
                                <td>Processing Overview</td>
                                <td class="text-secondary">A newly created or freshly installed examination starts with its processing modules in <strong class="text-body">Not Started</strong>. Status changes only after the relevant authoritative processing begins.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
