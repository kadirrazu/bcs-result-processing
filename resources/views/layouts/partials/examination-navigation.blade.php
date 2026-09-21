@if ($activeExamination)
    @php
        $workspaceItems = collect(config('navigation.examination.items', []));
        $overviewItem = $workspaceItems->firstWhere('label', 'Overview');

        $workspaceGroups = [
            'Core Processing' => ['Registrations', 'Preliminary', 'Written', 'Viva', 'Tabulation'],
            'Result Processing' => ['Circular', 'Merit', 'Choice Validation', 'Choice Optimization', 'Allocation', 'Reporting'],
            'Non-Cadre Processing' => ['Non Cadre Processing'],
        ];

        $resolveWorkspaceItem = static function (array $item): array {
            $routeName = $item['route'] ?? null;
            $routeAvailable = is_string($routeName) && \Illuminate\Support\Facades\Route::has($routeName);
            $isActive = request()->routeIs($item['patterns'] ?? []);
            $moduleReady = true;

            if (($item['requires_non_cadre_ready'] ?? false) && $routeAvailable) {
                try {
                    $moduleReady = (bool) (app(\App\Services\NonCadre\NonCadreReadinessService::class)->inspect()['ready'] ?? false);
                } catch (\Throwable) {
                    $moduleReady = false;
                }
            }

            return [$routeName, $routeAvailable, $isActive, $moduleReady];
        };

        $activeWorkspaceItem = $workspaceItems->first(function (array $item) {
            return request()->routeIs($item['patterns'] ?? []);
        });
    @endphp

    <div class="d-print-none border-top border-bottom bg-surface app-examination-nav">
        <div class="container-xl py-2">
            <div class="d-flex flex-column flex-lg-row align-items-lg-center gap-2 gap-lg-3">
                <div class="d-flex align-items-center justify-content-between gap-3 flex-shrink-0">
                    <div>
                        <div class="small text-secondary text-uppercase fw-semibold" style="letter-spacing:.045em;">Processing workspace</div>
                        <div class="fw-bold text-body text-truncate" style="max-width:22rem;" title="{{ $activeExamination->name }}">
                            {{ $activeExamination->name }}
                        </div>
                    </div>

                    @if ($activeWorkspaceItem)
                        <span class="badge bg-primary-lt d-lg-none text-nowrap">
                            {{ $activeWorkspaceItem['label'] }}
                        </span>
                    @endif
                </div>

                <div class="d-flex flex-wrap align-items-center gap-2 flex-grow-1">
                    @if ($overviewItem)
                        @php
                            [$overviewRoute, $overviewAvailable, $overviewActive, $overviewReady] = $resolveWorkspaceItem($overviewItem);
                        @endphp
                        @if ($overviewAvailable && $overviewReady)
                            <a href="{{ route($overviewRoute) }}"
                               class="btn btn-sm {{ $overviewActive ? 'btn-primary' : 'btn-outline-secondary' }}">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-1"><path d="M4 4h6v6H4z"/><path d="M14 4h6v6h-6z"/><path d="M4 14h6v6H4z"/><path d="M14 14h6v6h-6z"/></svg>
                                Overview
                            </a>
                        @else
                            <button type="button" class="btn btn-sm btn-outline-secondary disabled" disabled>Overview</button>
                        @endif
                    @endif

                    @foreach ($workspaceGroups as $groupLabel => $labels)
                        @php
                            $groupItems = $workspaceItems->filter(fn (array $item) => in_array($item['label'] ?? '', $labels, true))->values();
                            $groupActive = $groupItems->contains(fn (array $item) => request()->routeIs($item['patterns'] ?? []));
                            $activeInGroup = $groupItems->first(fn (array $item) => request()->routeIs($item['patterns'] ?? []));
                        @endphp

                        @if ($groupItems->isNotEmpty())
                            <div class="dropdown">
                                <button class="btn btn-sm {{ $groupActive ? 'btn-primary' : 'btn-outline-secondary' }} dropdown-toggle"
                                        type="button"
                                        data-bs-toggle="dropdown"
                                        data-bs-auto-close="true"
                                        aria-expanded="false">
                                    {{ $groupLabel }}
                                    @if ($activeInGroup)
                                        <span class="ms-1 opacity-75 d-none d-xl-inline">· {{ $activeInGroup['label'] }}</span>
                                    @endif
                                </button>
                                <div class="dropdown-menu shadow-sm p-2" style="min-width:18rem;">
                                    <div class="px-2 pt-1 pb-2 small text-secondary text-uppercase fw-semibold" style="letter-spacing:.04em;">
                                        {{ $groupLabel }}
                                    </div>

                                    @foreach ($groupItems as $item)
                                        @php
                                            [$routeName, $routeAvailable, $isActive, $moduleReady] = $resolveWorkspaceItem($item);
                                        @endphp

                                        @if ($routeAvailable && $moduleReady)
                                            <a class="dropdown-item d-flex align-items-center justify-content-between gap-3 rounded {{ $isActive ? 'active' : '' }}"
                                               href="{{ route($routeName) }}">
                                                <span class="fw-medium">{{ $item['label'] }}</span>
                                                @if ($isActive)
                                                    <span class="badge {{ $isActive ? 'bg-white text-primary' : 'bg-primary-lt' }}">Current</span>
                                                @endif
                                            </a>
                                        @else
                                            <span class="dropdown-item d-flex align-items-center justify-content-between gap-3 rounded disabled"
                                                  aria-disabled="true"
                                                  title="Module is unavailable until its required upstream authority is current">
                                                <span>{{ $item['label'] }}</span>
                                                <span class="badge bg-secondary-lt">Locked</span>
                                            </span>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>

                @if ($activeWorkspaceItem)
                    <div class="d-none d-lg-flex align-items-center gap-2 ms-lg-auto flex-shrink-0">
                        <span class="small text-secondary">Current</span>
                        <span class="badge bg-primary-lt text-nowrap px-2 py-2">{{ $activeWorkspaceItem['label'] }}</span>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endif
