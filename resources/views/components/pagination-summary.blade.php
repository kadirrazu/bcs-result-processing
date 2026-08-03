@props(['paginator'])
@if($paginator->total() > 0)
    @php
        $hasFilters = collect(request()->except('page'))
            ->reject(fn ($value) => $value === null || $value === '' || $value === 'all' || $value === false)
            ->isNotEmpty();
    @endphp
    <div class="text-secondary small">
        Displaying {{ number_format((int) $paginator->firstItem()) }} to {{ number_format((int) $paginator->lastItem()) }}
        of {{ number_format((int) $paginator->total()) }} {{ $hasFilters ? 'matching ' : '' }}records
    </div>
@endif
