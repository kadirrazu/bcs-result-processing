@if($summary && $cadre)
<div class="{{ $class ?? 'avr-summary' }}">
    <span class="avr-sum-item avr-sum-post">
        Total Post of {{ $cadre['abbr'] }} ({{ $cadre['code'] }}):
        <strong>{{ number_format($summary['total_post']) }}</strong>
    </span>
    <span class="avr-sum-sep">|</span>
    <span class="avr-sum-item avr-sum-eligible">
        Total Allocation Eligible of {{ $cadre['abbr'] }} ({{ $cadre['code'] }}):
        <strong>{{ number_format($summary['eligible']) }}</strong>
    </span>
    <span class="avr-sum-sep">|</span>
    <span class="avr-sum-item avr-sum-allocated">
        Allocated in {{ $cadre['abbr'] }} ({{ $cadre['code'] }}):
        <strong>{{ number_format($summary['allocated']) }}</strong>
    </span>
    <span class="avr-sum-sep">|</span>
    <span class="avr-sum-item avr-sum-nonallocated">
        Non-Allocated in {{ $cadre['abbr'] }} ({{ $cadre['code'] }}):
        <strong>{{ number_format($summary['non_allocated']) }}</strong>
    </span>
    <span class="avr-sum-sep">|</span>
    <span class="avr-sum-item avr-sum-other">
        Allocated in Other Cadres:
        <strong>{{ number_format($summary['allocated_in_other_cadres']) }}</strong>
    </span>
    <span class="avr-sum-sep">|</span>
    <span class="avr-sum-item avr-sum-none">
        Not Allocated in Any Cadre:
        <strong>{{ number_format($summary['not_allocated_anywhere']) }}</strong>
    </span>
</div>
@endif
