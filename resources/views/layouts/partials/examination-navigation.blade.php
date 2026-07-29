@if ($activeExamination)
    <div class="navbar-expand-md d-print-none app-examination-nav">
        <div class="navbar navbar-light">
            <div class="container-xl">
                <div class="d-flex align-items-center w-100 overflow-auto">
                    <div class="me-3 flex-shrink-0">
                        <div class="small text-secondary">Processing workspace</div>
                        <div class="fw-semibold text-nowrap">{{ $activeExamination->name }}</div>
                    </div>
                    <ul class="navbar-nav flex-row flex-nowrap app-examination-menu">
                        @foreach (config('navigation.examination.items', []) as $item)
                            @php
                                $routeName = $item['route'] ?? null;
                                $routeAvailable = is_string($routeName) && \Illuminate\Support\Facades\Route::has($routeName);
                                $isActive = request()->routeIs($item['patterns'] ?? []);
                            @endphp
                            <li class="nav-item {{ $isActive ? 'active' : '' }}">
                                @if ($routeAvailable)
                                    <a class="nav-link" href="{{ route($routeName) }}"><span class="nav-link-title text-nowrap">{{ $item['label'] }}</span></a>
                                @else
                                    <span class="nav-link disabled" aria-disabled="true" title="Module will be enabled when its route is added"><span class="nav-link-title text-nowrap">{{ $item['label'] }}</span></span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    </div>
@endif
