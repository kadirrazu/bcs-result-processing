@extends('layouts.app')
@section('content')
<div class="page-header d-print-none"><div class="container-xl"><div class="row g-2 align-items-center"><div class="col"><h2 class="page-title">A6 — Dynamic Excel Export Builder</h2><div class="text-secondary">Build one combined workbook from selected finalized module fields.</div></div><div class="col-auto ms-auto"><a class="btn btn-outline-secondary" href="{{ route('allocation.a6.index') }}">Back to A6 — Reporting &amp; Export</a></div></div></div></div>
<div class="page-body"><div class="container-xl">
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<form method="POST" action="{{ route('allocation.a6.exports.excel-builder.start') }}">@csrf
<div class="card mb-3"><div class="card-header"><h3 class="card-title">Candidate Scope</h3></div><div class="card-body"><div class="row g-3 align-items-end">
    <div class="col-md-5"><label class="form-label">Scope</label><select class="form-select" id="a6-excel-scope" name="scope"><option value="tabulation_eligible" @selected(old('scope')==='tabulation_eligible')>All Viva Passed / Tabulation-Eligible</option><option value="allocated" @selected(old('scope')==='allocated')>Only Allocated Candidates</option><option value="cadre" @selected(old('scope')==='cadre')>Specific Cadre</option></select></div>
    <div class="col-md-5" id="a6-cadre-wrap"><label class="form-label">Cadre</label><select class="form-select" name="cadre_code"><option value="">Select cadre</option>@foreach($cadres as $row)<option value="{{ $row['code'] }}" @selected((string)old('cadre_code')===(string)$row['code'])>{{ $row['code'] }} - {{ $row['abbr'] }} ({{ number_format($row['allocated']) }})</option>@endforeach</select></div>
</div></div></div>

<div class="d-flex justify-content-between align-items-center mb-2"><div><h3 class="mb-0">Select Fields</h3><div class="text-secondary small">SL is always included. Field order follows the module order below. Mapped companion columns are added automatically beside selected code fields.</div></div><div class="btn-list"><button type="button" class="btn btn-sm btn-outline-secondary" id="a6-select-all">Select All</button><button type="button" class="btn btn-sm btn-outline-secondary" id="a6-clear-all">Clear All</button></div></div>
<div class="row row-cards mb-3">
@foreach($groups as $groupKey => $group)
<div class="col-md-6 col-xl-4"><div class="card h-100"><div class="card-header"><h3 class="card-title">{{ $group['label'] }}</h3><div class="ms-auto btn-list"><button type="button" class="btn btn-sm btn-ghost-primary a6-group-select" data-group="{{ $groupKey }}">Select Group</button><button type="button" class="btn btn-sm btn-ghost-secondary a6-group-clear" data-group="{{ $groupKey }}">Clear Group</button></div></div><div class="card-body">
    @foreach($group['fields'] as $key => $label)
    <label class="form-check mb-2"><input class="form-check-input a6-field a6-group-{{ $groupKey }}" type="checkbox" name="fields[]" value="{{ $key }}" @checked(in_array($key,(array)old('fields',[]),true))><span class="form-check-label">{{ $label }}</span></label>
    @endforeach
</div></div></div>
@endforeach
</div>
<div class="card"><div class="card-body d-flex justify-content-between align-items-center"><div><div class="fw-bold"><span id="a6-selected-count">0</span> fields selected</div><div class="small text-secondary">The exact selected field configuration is stored with export provenance/audit.</div></div><button class="btn btn-success">Queue Custom Excel Export</button></div></div>
</form>
</div></div>
<script>
(() => {
    const fields = [...document.querySelectorAll('.a6-field')];
    const count = document.getElementById('a6-selected-count');
    const scope = document.getElementById('a6-excel-scope');
    const cadreWrap = document.getElementById('a6-cadre-wrap');
    const refresh = () => { count.textContent = fields.filter(x => x.checked).length; cadreWrap.classList.toggle('d-none', scope.value !== 'cadre'); };
    document.getElementById('a6-select-all').addEventListener('click', () => { fields.forEach(x => x.checked = true); refresh(); });
    document.getElementById('a6-clear-all').addEventListener('click', () => { fields.forEach(x => x.checked = false); refresh(); });
    document.querySelectorAll('.a6-group-select').forEach(button => button.addEventListener('click', () => { document.querySelectorAll('.a6-group-' + button.dataset.group).forEach(x => x.checked = true); refresh(); }));
    document.querySelectorAll('.a6-group-clear').forEach(button => button.addEventListener('click', () => { document.querySelectorAll('.a6-group-' + button.dataset.group).forEach(x => x.checked = false); refresh(); }));
    fields.forEach(x => x.addEventListener('change', refresh)); scope.addEventListener('change', refresh); refresh();
})();
</script>
@endsection
