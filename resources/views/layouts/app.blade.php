<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1, viewport-fit=cover"
    >

    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>
        @yield('title', 'Dashboard') | {{ config('app.name') }}
    </title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @stack('styles')
</head>

<body>
    <div class="page">
        <header class="navbar navbar-expand-md d-print-none">
            <div class="container-xl">
                <button
                    class="navbar-toggler"
                    type="button"
                    data-bs-toggle="collapse"
                    data-bs-target="#navbar-menu"
                    aria-controls="navbar-menu"
                    aria-expanded="false"
                    aria-label="Toggle navigation"
                >
                    <span class="navbar-toggler-icon"></span>
                </button>

                <h1 class="navbar-brand navbar-brand-autodark pe-0 pe-md-3">
                    <a
                        href="{{ route('dashboard') }}"
                        class="text-decoration-none app-brand-title"
                    >
                        BCS Result Processing System
                    </a>
                </h1>

                <div class="navbar-nav flex-row order-md-last">

                    @php
                        $activeExamination = app(
                            \App\Support\Examinations\ExaminationContext::class
                        )->current();
                    @endphp

                    @if ($activeExamination)
                        <div class="nav-item me-3">
                            <span class="badge bg-success-lt">
                                {{ $activeExamination->name }}
                            </span>
                        </div>
                    @endif

                    @can('viewAny', \App\Models\Examination::class)
                        <li class="nav-item {{ request()->routeIs('examinations.*') ? 'active' : '' }} me-2">
                            <a
                                class="nav-link"
                                href="{{ route('examinations.index') }}"
                            >
                                <span class="nav-link-title">
                                    Examinations
                                </span>
                            </a>
                        </li>
                    @endcan

                    @can('viewAny', \App\Models\User::class)
                    <li class="nav-item {{ request()->routeIs('users.*') ? 'active' : '' }} me-2">
                        <a class="nav-link" href="{{ route('users.index') }}">
                            <span class="nav-link-title">
                                Users
                            </span>
                        </a>
                    </li>
                    @endcan

                    <div class="nav-item dropdown">
                        <a
                            href="#"
                            class="nav-link d-flex lh-1 text-reset p-0"
                            data-bs-toggle="dropdown"
                            aria-label="Open user menu"
                        >
                            <span class="avatar avatar-sm">
                                {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                            </span>

                            <div class="d-none d-xl-block ps-2">
                                <div class="d-none d-xl-block ps-2">
                                    <div>
                                        {{ auth()->user()->name }}
                                    </div>

                                    <div class="mt-1 small text-secondary">
                                        {{ auth()->user()->designation?->name ?? 'Designation not assigned' }}
                                    </div>

                                    <div class="mt-1 small text-secondary">
                                        {{ auth()->user()->email }}
                                    </div>
                                </div>
                            </div>
                        </a>

                        <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
                            <form
                                method="POST"
                                action="{{ route('logout') }}"
                            >
                                @csrf

                                <button
                                    type="submit"
                                    class="dropdown-item"
                                >
                                    Sign out
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <div
                    class="collapse navbar-collapse"
                    id="navbar-menu"
                >
                    <div class="d-flex flex-column flex-md-row flex-fill align-items-stretch align-items-md-center">
                        <ul class="navbar-nav">
                            <li class="nav-item">
                                <a
                                    class="nav-link"
                                    href="{{ route('dashboard') }}"
                                >
                                    <span class="nav-link-title">
                                        Dashboard
                                    </span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </header>

        <div class="page-wrapper">
            @hasSection('page-header')
                <div class="page-header d-print-none">
                    <div class="container-xl">
                        @yield('page-header')
                    </div>
                </div>
            @endif

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

                    @yield('content')
                </div>
            </div>

            <footer class="footer footer-transparent d-print-none">
                <div class="container-xl">
                    <div class="row">
                        <div class="col-md-6">
                            Crafted with ❤️ by "Md. Abdul Kadir - Programmer - BPSC";
                        </div>
                        <div class="col-md-6 text-end">
                            Version - Baseline 1.0
                        </div>
                    </div>
                </div>
            </footer>
        </div>
    </div>

    @stack('scripts')
</body>
</html>