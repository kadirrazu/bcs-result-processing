<div class="collapse navbar-collapse" id="main-navigation">
    <ul class="navbar-nav">
        <li class="nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <a class="nav-link" href="{{ route('dashboard') }}"><span class="nav-link-title">Dashboard</span></a>
        </li>

        @can('viewAny', \App\Models\Examination::class)
            <li class="nav-item {{ request()->routeIs('examinations.*') ? 'active' : '' }}">
                <a class="nav-link" href="{{ route('examinations.index') }}"><span class="nav-link-title">Examinations</span></a>
            </li>
        @endcan

        @can('viewAny', \App\Models\CadreMaster::class)
            <li class="nav-item dropdown {{ request()->routeIs(['cadre-masters.*', 'cadre-sub-masters.*', 'bachelor-subjects.*', 'post-related-subjects.*', 'master-data.*', 'registration-masters.*', 'previous-bcs-repository.*']) ? 'active' : '' }}">
                <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown" role="button" aria-expanded="false">
                    <span class="nav-link-title">Master Data</span>
                </a>

                <div class="dropdown-menu">
                    <a class="dropdown-item {{ request()->routeIs('cadre-masters.*') ? 'active' : '' }}" href="{{ route('cadre-masters.index') }}">Cadre Master</a>
                    <a class="dropdown-item {{ request()->routeIs('cadre-sub-masters.*') ? 'active' : '' }}" href="{{ route('cadre-sub-masters.index') }}">Sub Cadre Master</a>
                    <a class="dropdown-item {{ request()->routeIs('bachelor-subjects.*') ? 'active' : '' }}" href="{{ route('bachelor-subjects.index') }}">Bachelor Subjects</a>
                    <a class="dropdown-item {{ request()->routeIs('post-related-subjects.*') ? 'active' : '' }}" href="{{ route('post-related-subjects.index') }}">Post-related Subjects</a>

                    {{-- Registration reference masters live in the central database. --}}
                    <div class="dropdown-divider"></div>
                    <h6 class="dropdown-header">Registration Masters</h6>
                    <a class="dropdown-item {{ request()->routeIs('registration-masters.*') && request()->route('type') === 'genders' ? 'active' : '' }}" href="{{ route('registration-masters.index', 'genders') }}">Genders</a>
                    <a class="dropdown-item {{ request()->routeIs('registration-masters.*') && request()->route('type') === 'divisions' ? 'active' : '' }}" href="{{ route('registration-masters.index', 'divisions') }}">Divisions</a>
                    <a class="dropdown-item {{ request()->routeIs('registration-masters.*') && request()->route('type') === 'districts' ? 'active' : '' }}" href="{{ route('registration-masters.index', 'districts') }}">Districts</a>
                    <a class="dropdown-item {{ request()->routeIs('registration-masters.*') && request()->route('type') === 'universities' ? 'active' : '' }}" href="{{ route('registration-masters.index', 'universities') }}">Universities</a>
                    <div class="dropdown-divider"></div>
                    <h6 class="dropdown-header">Historical Data</h6>
                    <a class="dropdown-item {{ request()->routeIs('previous-bcs-repository.*') ? 'active' : '' }}" href="{{ route('previous-bcs-repository.index') }}">Previous BCS Repository</a>
                </div>
            </li>
            
        @endcan

        @can('viewAny', \App\Models\User::class)
            <li class="nav-item {{ request()->routeIs('users.*') ? 'active' : '' }}">
                <a class="nav-link" href="{{ route('users.index') }}"><span class="nav-link-title">Users</span></a>
            </li>
        @endcan
    </ul>
</div>
