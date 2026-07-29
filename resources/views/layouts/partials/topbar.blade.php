<header class="navbar navbar-expand-md navbar-light d-print-none app-topbar">
    <div class="container-xl">
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#main-navigation" aria-controls="main-navigation" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <h1 class="navbar-brand navbar-brand-autodark pe-0 pe-md-3">
            <a href="{{ route('dashboard') }}" class="text-decoration-none app-brand-title">BCS Result Processing System</a>
        </h1>

        <div class="navbar-nav flex-row order-md-last align-items-center gap-2">
            @if ($activeExamination)
                <div class="nav-item d-none d-sm-flex">
                    <span class="badge bg-success-lt app-active-examination" title="Active examination">{{ $activeExamination->name }}</span>
                </div>
            @endif
            @include('layouts.partials.user-menu')
        </div>

        @include('layouts.partials.main-navigation')
    </div>
</header>
