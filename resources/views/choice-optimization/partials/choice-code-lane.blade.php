@php
    $laneCodes = array_values((array) ($codes ?? []));
    $laneBadge = $badgeClass ?? 'bg-secondary-lt';
@endphp

<div class="d-flex flex-wrap gap-1 pb-1" style="max-width:100%">
    @forelse($laneCodes as $i => $code)
        @php
            $abbr = $choiceCodeAbbrMap[(int)$code] ?? '—';
        @endphp
        <span class="badge {{ $laneBadge }} d-inline-flex flex-column align-items-center justify-content-center flex-shrink-0 px-2 py-1 text-center"
              style="min-width:50px; line-height:1.1">
            <span class="small text-secondary">#{{ str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT) }}</span>
            <span class="fw-bold">{{ $code }}</span>
            <span class="small">{{ $abbr }}</span>
        </span>
    @empty
        <span class="text-secondary">{{ $emptyText ?? 'None' }}</span>
    @endforelse
</div>
