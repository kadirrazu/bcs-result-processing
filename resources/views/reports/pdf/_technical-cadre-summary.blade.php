@if($summary && $cadre)
<div class="summary{{ !empty($footer) ? ' summary-footer' : '' }}">
    <span class="sum-item sum-post">
        Total Post of {{ $cadre['abbr'] }} ({{ $cadre['code'] }}):
        <strong>{{ number_format($summary['total_post']) }}</strong>
    </span>
    <span class="sum-sep">|</span>
    <span class="sum-item sum-eligible">
        Total Allocation Eligible of {{ $cadre['abbr'] }} ({{ $cadre['code'] }}):
        <strong>{{ number_format($summary['eligible']) }}</strong>
    </span>
    <span class="sum-sep">|</span>
    <span class="sum-item sum-allocated">
        Allocated in {{ $cadre['abbr'] }} ({{ $cadre['code'] }}):
        <strong>{{ number_format($summary['allocated']) }}</strong>
    </span>
    <span class="sum-sep">|</span>
    <span class="sum-item sum-nonallocated">
        Non-Allocated in {{ $cadre['abbr'] }} ({{ $cadre['code'] }}):
        <strong>{{ number_format($summary['non_allocated']) }}</strong>
    </span>
    <span class="sum-sep">|</span>
    <span class="sum-item sum-other">
        Allocated in Other Cadres:
        <strong>{{ number_format($summary['allocated_in_other_cadres']) }}</strong>
    </span>
    <span class="sum-sep">|</span>
    <span class="sum-item sum-none">
        Not Allocated in Any Cadre:
        <strong>{{ number_format($summary['not_allocated_anywhere']) }}</strong>
    </span>
</div>
@endif
