@extends('layouts.app')

@section('title', 'Users')

@section('content')
    <div class="page-header d-print-none">
        <div class="container-xl">
            <div class="row g-2 align-items-center">
                <div class="col">
                    <div class="page-pretitle">
                        Administration
                    </div>

                    <h2 class="page-title">
                        Users
                    </h2>
                </div>

                <div class="col-auto ms-auto">
                    <a
                        href="{{ route('users.create') }}"
                        class="btn btn-primary"
                    >
                        Add User
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="page-body">
        <div class="container-xl">
            @if (session('success'))
                <div
                    class="alert alert-success alert-dismissible"
                    role="alert"
                >
                    {{ session('success') }}

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="alert"
                        aria-label="Close"
                    ></button>
                </div>
            @endif

            <div class="card">
                <div class="card-header">
                    <form
                        method="GET"
                        action="{{ route('users.index') }}"
                        class="row g-2 w-100"
                    >
                        <div class="col-md-5">
                            <input
                                type="search"
                                name="search"
                                value="{{ $search }}"
                                class="form-control"
                                placeholder="Search name, email or designation"
                            >
                        </div>

                        <div class="col-auto">
                            <button class="btn btn-outline-primary">
                                Search
                            </button>
                        </div>

                        @if ($search !== '')
                            <div class="col-auto">
                                <a
                                    href="{{ route('users.index') }}"
                                    class="btn btn-outline-secondary"
                                >
                                    Clear
                                </a>
                            </div>
                        @endif
                    </form>
                </div>

                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Designation</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Last Login</th>
                                <th class="w-1"></th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse ($users as $user)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">
                                            {{ $user->name }}
                                        </div>

                                        <div class="text-secondary small">
                                            {{ $user->email }}
                                        </div>
                                    </td>

                                    <td>
                                        {{ $user->designation?->name ?? 'Not assigned' }}
                                    </td>

                                    <td>
                                        <span class="badge bg-blue-lt">
                                            {{ $user->role->label() }}
                                        </span>
                                    </td>

                                    <td>
                                        @if ($user->is_active)
                                            <span class="badge bg-success-lt">
                                                Active
                                            </span>
                                        @else
                                            <span class="badge bg-danger-lt">
                                                Inactive
                                            </span>
                                        @endif
                                    </td>

                                    <td>
                                        {{ $user->last_login_at?->format('d M Y, h:i A') ?? 'Never' }}
                                    </td>

                                    <td>
                                        <a
                                            href="{{ route('users.edit', $user) }}"
                                            class="btn btn-sm btn-outline-primary"
                                        >
                                            Edit
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td
                                        colspan="6"
                                        class="text-center text-secondary py-4"
                                    >
                                        No users found.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($users->hasPages())
                    <div class="card-footer">
                        {{ $users->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection