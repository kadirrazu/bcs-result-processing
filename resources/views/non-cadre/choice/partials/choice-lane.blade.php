@php($laneCodes = array_values((array)($codes ?? [])))
<div class="d-flex flex-wrap gap-2 pb-1 w-100" style="max-width:100%">
@forelse($laneCodes as $i => $code)
    <span class="badge {{ $badgeClass ?? 'bg-secondary-lt' }} d-inline-flex flex-column align-items-center justify-content-center flex-shrink-0 px-3 py-2" style="line-height:1.15; min-width:64px">
        <span class="small text-secondary">#{{ str_pad((string)($i+1),2,'0',STR_PAD_LEFT) }}</span>
        <span class="fw-bold mt-1">{{ $code }}</span>
    </span>
@empty
    <span class="text-secondary">{{ $emptyText ?? '—' }}</span>
@endforelse
</div>
