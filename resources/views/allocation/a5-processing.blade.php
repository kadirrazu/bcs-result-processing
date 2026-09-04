@extends('layouts.app')
@section('content')
<div class="page-header"><div class="container-xl"><div class="d-flex justify-content-between align-items-center"><div><h2 class="page-title">A5 — Final Allocation Validity Check</h2><div class="text-secondary">Run v{{ $a5Run->version }} · Source A4 v{{ $a5Run->a4Run?->version }}</div></div><a class="btn btn-sm btn-outline-secondary" href="{{ route('allocation.index') }}">Back to Allocation</a></div></div></div>
<div class="page-body"><div class="container-xl"><div class="card" id="a5-processing" data-status-url="{{ route('allocation.a5.status',$a5Run) }}"><div class="card-body">
<div class="d-flex justify-content-between mb-2"><strong id="a5-phase">{{ strtoupper(str_replace('_',' ',$a5Run->phase)) }}</strong><span><span id="a5-percent">{{ $a5Run->progress_percent }}</span>%</span></div>
<div class="progress"><div id="a5-bar" class="progress-bar" style="width:{{ $a5Run->progress_percent }}%"></div></div>
<div id="a5-message" class="text-secondary mt-2">{{ $a5Run->progress_message }}</div>
<div id="a5-counter" class="small text-secondary mt-1 {{ $a5Run->progress_total ? '' : 'd-none' }}">Processed {{ number_format($a5Run->progress_current) }} / {{ number_format($a5Run->progress_total) }}</div>
<div id="a5-error" class="alert alert-danger mt-3 {{ $a5Run->status === 'failed' ? '' : 'd-none' }}">{{ $a5Run->failure_message }}</div>
</div></div></div></div>
<script>
(function(){const wrap=document.getElementById('a5-processing');const url=wrap.dataset.statusUrl;async function poll(){try{const r=await fetch(url,{headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}});if(!r.ok)throw new Error('Unable to read A5 status.');const d=await r.json();document.getElementById('a5-percent').textContent=d.progress_percent||0;document.getElementById('a5-bar').style.width=(d.progress_percent||0)+'%';document.getElementById('a5-phase').textContent=String(d.phase||d.status||'').replaceAll('_',' ').toUpperCase();document.getElementById('a5-message').textContent=d.message||'';const c=document.getElementById('a5-counter');if(Number(d.progress_total||0)>0){c.textContent='Processed '+Number(d.progress_current||0).toLocaleString()+' / '+Number(d.progress_total||0).toLocaleString();c.classList.remove('d-none')}else c.classList.add('d-none');if(d.status==='failed'){const e=document.getElementById('a5-error');e.textContent=d.error||'A5 failed.';e.classList.remove('d-none');return}if(['validated_ok','validated_failed','finalized'].includes(d.status)){window.location=d.view_url;return}setTimeout(poll,1200)}catch(e){document.getElementById('a5-message').textContent=e.message+' Retrying…';setTimeout(poll,2500)}}setTimeout(poll,500)})();
</script>
@endsection
