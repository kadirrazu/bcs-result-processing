@if($quotaSummary)
<div class="avr-quota-summary {{ $class ?? '' }}">
    <span class="qsum qsum-total">Total Quota <strong>{{ number_format($quotaSummary['total']) }}</strong></span>
    <span class="qsep">·</span>
    <span class="qsum qsum-cff">CFF <strong>{{ number_format($quotaSummary['cff']) }}</strong></span>
    <span class="qsep">·</span>
    <span class="qsum qsum-em">EM <strong>{{ number_format($quotaSummary['em']) }}</strong></span>
    <span class="qsep">·</span>
    <span class="qsum qsum-phc">PHC <strong>{{ number_format($quotaSummary['phc']) }}</strong></span>
    <span class="qsep">·</span>
    <span class="qsum qsum-allocated">Allocated <strong>{{ number_format($quotaSummary['allocated']) }}</strong></span>
    <span class="qsep">·</span>
    <span class="qsum qsum-none">Not Allocated <strong>{{ number_format($quotaSummary['not_allocated']) }}</strong></span>
    <br>
    <span class="qsum qsum-mq">Allocated by MQ <strong>{{ number_format($quotaSummary['allocated_mq']) }}</strong></span>
    <span class="qsep">·</span>
    <span class="qsum qsum-cff">CFF <strong>{{ number_format($quotaSummary['allocated_cff']) }}</strong></span>
    <span class="qsep">·</span>
    <span class="qsum qsum-em">EM <strong>{{ number_format($quotaSummary['allocated_em']) }}</strong></span>
    <span class="qsep">·</span>
    <span class="qsum qsum-phc">PHC <strong>{{ number_format($quotaSummary['allocated_phc']) }}</strong></span>
</div>
@endif
