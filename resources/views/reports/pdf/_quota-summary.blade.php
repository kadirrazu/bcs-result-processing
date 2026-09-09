@if($quotaSummary)
<div class="quota-summary">
<span class="q-total">Total Quota <strong>{{ number_format($quotaSummary['total']) }}</strong></span>
<span class="q-sep"> · </span>
<span class="q-cff">CFF <strong>{{ number_format($quotaSummary['cff']) }}</strong></span>
<span class="q-sep"> · </span>
<span class="q-em">EM <strong>{{ number_format($quotaSummary['em']) }}</strong></span>
<span class="q-sep"> · </span>
<span class="q-phc">PHC <strong>{{ number_format($quotaSummary['phc']) }}</strong></span>
<span class="q-sep"> · </span>
<span class="q-allocated">Allocated <strong>{{ number_format($quotaSummary['allocated']) }}</strong></span>
<span class="q-sep"> · </span>
<span class="q-none">Not Allocated <strong>{{ number_format($quotaSummary['not_allocated']) }}</strong></span>
<br>
<span class="q-mq">Allocated by MQ <strong>{{ number_format($quotaSummary['allocated_mq']) }}</strong></span>
<span class="q-sep"> · </span>
<span class="q-cff">CFF <strong>{{ number_format($quotaSummary['allocated_cff']) }}</strong></span>
<span class="q-sep"> · </span>
<span class="q-em">EM <strong>{{ number_format($quotaSummary['allocated_em']) }}</strong></span>
<span class="q-sep"> · </span>
<span class="q-phc">PHC <strong>{{ number_format($quotaSummary['allocated_phc']) }}</strong></span>
</div>
@endif
