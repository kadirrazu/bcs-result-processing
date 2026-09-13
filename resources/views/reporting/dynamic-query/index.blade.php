@extends('layouts.app')
@section('title', 'Dynamic Query Builder')

@section('page-header')
<div class="row g-2 align-items-center">
    <div class="col">
        <div class="page-pretitle">{{ $examination->name }} · Reporting</div>
        <h2 class="page-title">Dynamic Query Builder</h2>
        <div class="text-secondary mt-1">Build controlled cross-module reports using system-approved business fields. Database structure and SQL remain internal.</div>
    </div>
    <div class="col-auto ms-auto"><a href="{{ route('examination-reports.index') }}" class="btn btn-outline-secondary">Back to Reporting</a></div>
</div>
@endsection

@section('content')
<div id="dynamic-query-builder"
     data-preview-url="{{ route('examination-reports.dynamic-query.preview') }}"
     data-save-url="{{ route('examination-reports.dynamic-query.saved.save') }}"
     data-saved-base-url="{{ url('/reports/dynamic-query/saved') }}"
     data-history-url="{{ route('examination-reports.dynamic-query.history') }}"
     data-export-url="{{ route('examination-reports.dynamic-query.export.xlsx') }}"
     data-export-pdf-url="{{ route('examination-reports.dynamic-query.export.pdf') }}"
     data-fields='@json($semanticFields)'
     data-saved-reports='@json($savedReports)'
     data-recent-runs='@json($recentRuns)'
     data-default-preview="{{ $defaultPreviewSize }}">
    <div class="row g-3">
        <div class="col-12">
            <div class="card">
                <div class="card-header"><div><div class="subheader">Reusable Reports</div><h3 class="card-title">Saved Reports</h3></div></div>
                <div class="card-body">
                    <div class="row g-3 align-items-end">
                        <div class="col-lg-5">
                            <label class="form-label">Saved Report</label>
                            <div class="input-group">
                                <select id="dq-saved-report" class="form-select"><option value="">New / Unsaved Report</option></select>
                                <button type="button" class="btn btn-outline-primary" id="dq-load-report">Load</button>
                            </div>
                        </div>
                        <div class="col-lg-4"><label class="form-label">Saved Report Name</label><input id="dq-save-name" class="form-control" maxlength="180" placeholder="e.g. Cadre-wise Quota Summary"></div>
                        <div class="col-lg-3"><label class="form-label">Description <span class="text-secondary">(optional)</span></label><textarea id="dq-save-description" class="form-control" rows="1" maxlength="1000"></textarea></div>
                        <div class="col-12">
                            <div class="d-flex flex-wrap gap-2">
                                <button type="button" class="btn btn-primary" id="dq-save-report">Save</button>
                                <button type="button" class="btn btn-outline-primary" id="dq-save-as">Save as New</button>
                                <button type="button" class="btn btn-outline-danger ms-auto" id="dq-delete-report" disabled>Delete</button>
                            </div>
                            <div id="dq-save-status" class="small text-secondary mt-2">Definitions are stored semantically; raw SQL is never saved.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card">
                <div class="card-header"><div><div class="subheader">Step 1</div><h3 class="card-title">Data &amp; Fields</h3></div><div class="card-actions"><button type="button" class="btn btn-sm btn-outline-secondary" id="dq-clear-fields">Clear Selected Fields</button></div></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <div class="border rounded p-3 bg-light-subtle">
                                <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                                    <div><strong>Selected Report Fields</strong><div class="text-secondary small">Click an available field to add it. Click a selected field here to remove it.</div></div>
                                    <span id="dq-selected-count" class="badge bg-blue-lt text-blue">0 selected</span>
                                </div>
                                <div id="dq-selected-chips" class="d-flex flex-wrap gap-2"><span class="text-secondary small">No report fields selected yet.</span></div>
                            </div>
                        </div>
                        <div class="col-lg-4"><input id="dq-field-search" class="form-control" placeholder="Search approved fields…"></div>
                        <div class="col-12"><div id="dq-field-list" style="max-height:420px;overflow:auto"></div></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card">
                <div class="card-header"><div><div class="subheader">Step 2</div><h3 class="card-title">Conditions</h3></div><div class="card-actions"><button type="button" class="btn btn-sm btn-outline-primary" id="dq-add-condition">Add Condition</button></div></div>
                <div class="card-body"><div id="dq-conditions"></div><div class="text-secondary small mt-2">Build nested AND/OR groups. Only system-approved fields and operators can be executed.</div></div>
            </div>
        </div>

        <div class="col-12">
            <div class="card">
                <div class="card-header"><div><div class="subheader">Step 3</div><h3 class="card-title">Sorting &amp; Report Configuration</h3></div></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4"><label class="form-label">Report Mode</label><select id="dq-mode" class="form-select"><option value="detail">Detail Report</option><option value="summary">Summary / Aggregate Report</option></select></div>
                        <div class="col-md-5"><label class="form-label">Report Title</label><input id="dq-title" class="form-control" value="Dynamic Query Report" maxlength="180"></div>
                        <div class="col-md-3"><label class="form-label">Preview Rows</label><select id="dq-preview-size" class="form-select">@foreach($previewSizes as $size)<option value="{{ $size }}" @selected($size === $defaultPreviewSize)>{{ $size }} rows</option>@endforeach</select></div>
                        <div class="col-12"><div id="dq-selected-fields" class="vstack gap-2"></div></div>
                        <div class="col-12" id="dq-summary-config" style="display:none">
                            <div class="border rounded p-3 bg-light-subtle">
                                <div class="d-flex justify-content-between align-items-center mb-2"><strong>Grouping &amp; Aggregates</strong><button type="button" class="btn btn-sm btn-outline-primary" id="dq-add-aggregate">Add Aggregate</button></div>
                                <div class="row g-2"><div class="col-md-6"><div class="d-flex justify-content-between align-items-center"><label class="form-label">Group By</label><button type="button" class="btn btn-sm btn-ghost-primary" id="dq-add-group">Add Group Field</button></div><div id="dq-groups" class="vstack gap-2"></div></div><div class="col-md-6"><div id="dq-aggregates" class="vstack gap-2"></div></div></div>
                            </div>
                        </div>
                        <div class="col-12"><div class="d-flex justify-content-between align-items-center mb-2"><label class="form-label mb-0">Sorting Priority</label><button type="button" class="btn btn-sm btn-outline-primary" id="dq-add-sort">Add Sort</button></div><div id="dq-sorts" class="vstack gap-2"></div><div class="text-secondary small mt-1">Sort rules are applied from top to bottom.</div></div>
                        <div class="col-12"><div class="d-flex flex-wrap gap-3"><label class="form-check"><input id="dq-show-serial" class="form-check-input" type="checkbox" checked><span class="form-check-label">Serial number</span></label><label class="form-check"><input id="dq-show-page" class="form-check-input" type="checkbox" checked><span class="form-check-label">Page number</span></label><label class="form-check"><input id="dq-show-time" class="form-check-input" type="checkbox" checked><span class="form-check-label">Generation timestamp</span></label></div></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card">
                <div class="card-header"><div><div class="subheader">Preview</div><h3 class="card-title">Live Preview</h3></div><div class="card-actions d-flex align-items-center gap-2"><span id="dq-count" class="badge bg-blue-lt text-blue">Not calculated</span><button type="button" class="btn btn-outline-primary" id="dq-export-xlsx">Generate XLSX</button><button type="button" class="btn btn-outline-primary" id="dq-export-pdf">Generate PDF</button><button type="button" class="btn btn-outline-secondary" id="dq-clear-preview">Clear Preview</button><button type="button" class="btn btn-primary" id="dq-preview">Run Preview</button></div></div>
                <div class="card-body"><div id="dq-message" class="text-secondary">Select fields and run a preview. Only the chosen preview rows are loaded; total matching count is calculated separately.</div><div id="dq-export-status" class="small text-secondary mt-2"></div><div id="dq-preview-table" class="table-responsive mt-3"></div></div>
            </div>
        </div>

        <div class="col-12">
            <div class="card">
                <div class="card-header"><div><div class="subheader">Audit</div><h3 class="card-title">Recent Executions</h3></div></div>
                <div class="card-body p-0"><div id="dq-history" class="list-group list-group-flush"></div></div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const root=document.getElementById('dynamic-query-builder'), fields=JSON.parse(root.dataset.fields||'[]'), byId=Object.fromEntries(fields.map(f=>[f.id,f])), selected=new Set();
    const labelOverrides={};
    let savedReports=JSON.parse(root.dataset.savedReports||'[]'), currentSavedReportId=null, recentRuns=JSON.parse(root.dataset.recentRuns||'[]');
    const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
    const csrf=()=>document.querySelector('meta[name="csrf-token"]')?.content||'';
    const opLabel=o=>({eq:'Equals',neq:'Not equal',contains:'Contains',starts_with:'Starts with',gt:'Greater than',gte:'Greater/equal',lt:'Less than',lte:'Less/equal',between:'Between',in:'In list',is_blank:'Is blank',is_not_blank:'Is not blank'})[o]||o;
    const typeLabel=t=>String(t||'string').toUpperCase();
    const fieldOptions=(predicate=()=>true)=>fields.filter(predicate).map(f=>`<option value="${esc(f.id)}">${esc(f.module)} · ${esc(f.label)} (${esc(typeLabel(f.type))})</option>`).join('');

    function renderSavedReports(){const select=document.getElementById('dq-saved-report'),value=currentSavedReportId?String(currentSavedReportId):'';select.innerHTML='<option value="">New / Unsaved Report</option>'+savedReports.map(r=>`<option value="${r.id}">${esc(r.name)} · v${r.version}</option>`).join('');select.value=value;document.getElementById('dq-delete-report').disabled=!currentSavedReportId;}
    function renderHistory(){const el=document.getElementById('dq-history'),rows=currentSavedReportId?recentRuns.filter(r=>Number(r.saved_report_id)===Number(currentSavedReportId)):recentRuns,typeLabel=t=>({preview:'Preview',xlsx_export:'XLSX Export',pdf_export:'PDF Export'})[t]||t;el.innerHTML=rows.length?rows.slice(0,10).map(r=>`<div class="list-group-item"><div class="d-flex justify-content-between gap-2"><strong class="small">${esc(typeLabel(r.run_type))} · v${r.version}</strong><span class="${r.status==='failed'?'text-danger':'text-secondary'} small">${esc(r.status||'completed')} · ${r.duration_ms} ms</span></div><div class="text-secondary small">${Number(r.matching_count).toLocaleString()} matching${r.run_type==='preview'?` · ${r.preview_size} preview rows`:''}</div><div class="text-secondary small">${r.executed_at?new Date(r.executed_at).toLocaleString():''}</div></div>`).join(''):'<div class="p-3 text-secondary small">No saved-report executions yet.</div>';}
    async function refreshHistory(){const url=new URL(root.dataset.historyUrl,window.location.origin);if(currentSavedReportId)url.searchParams.set('saved_report_id',currentSavedReportId);const res=await fetch(url,{headers:{Accept:'application/json'}});if(res.ok){recentRuns=(await res.json()).runs||[];renderHistory();}}

    function renderSelectedChips(){
        const ids=[...selected], box=document.getElementById('dq-selected-chips');
        document.getElementById('dq-selected-count').textContent=`${ids.length} selected`;
        box.innerHTML=ids.length?ids.map((id,i)=>`<button type="button" class="btn btn-sm btn-primary dq-selected-chip" data-id="${esc(id)}" title="Remove ${esc(byId[id].label)}"><span class="badge bg-white text-primary me-1">${i+1}</span>${esc(byId[id].label)} <span aria-hidden="true">×</span></button>`).join(''):'<span class="text-secondary small">No report fields selected yet.</span>';
        box.querySelectorAll('.dq-selected-chip').forEach(b=>b.onclick=()=>removeSelected(b.dataset.id));
    }
    function addSelected(id){if(!byId[id]||selected.has(id))return;selected.add(id);renderFieldList(document.getElementById('dq-field-search').value);renderSelected();renderSelectedChips();}
    function removeSelected(id){selected.delete(id);delete labelOverrides[id];renderFieldList(document.getElementById('dq-field-search').value);renderSelected();renderSelectedChips();}
    function moveSelected(id,delta){const ids=[...selected],from=ids.indexOf(id),to=from+delta;if(from<0||to<0||to>=ids.length)return;[ids[from],ids[to]]=[ids[to],ids[from]];selected.clear();ids.forEach(x=>selected.add(x));renderSelected();renderSelectedChips();}
    function renderFieldList(filter='') {
        const q=filter.trim().toLowerCase(), groups={};
        fields.filter(f=>!q||`${f.label} ${f.module}`.toLowerCase().includes(q)).forEach(f=>(groups[f.module]||=[]).push(f));
        document.getElementById('dq-field-list').innerHTML=Object.entries(groups).map(([m,a])=>`
            <section class="dq-field-module border rounded p-3 mb-3" data-module="${esc(m)}">
                <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                    <div><span class="subheader">Module</span> <strong>${esc(m)}</strong></div>
                    <span class="badge bg-secondary-lt">${a.length} field${a.length===1?'':'s'}</span>
                </div>
                <div class="d-flex flex-wrap gap-2 dq-field-chip-list">
                    ${a.map(f=>`<button type="button" class="btn btn-sm ${selected.has(f.id)?'btn-primary':'btn-outline-secondary'} dq-field-chip" data-id="${esc(f.id)}" title="${esc(f.type)}">${esc(f.label)} (${esc(typeLabel(f.type))})${selected.has(f.id)?' ✓':''}</button>`).join('')}
                </div>
            </section>`).join('')||'<div class="text-secondary">No approved fields found.</div>';
        document.querySelectorAll('.dq-field-chip').forEach(b=>b.onclick=()=>selected.has(b.dataset.id)?removeSelected(b.dataset.id):addSelected(b.dataset.id));
    }
    function renderSelected(labels={}){
        Object.entries(labels||{}).forEach(([id,label])=>labelOverrides[id]=label);
        const ids=[...selected];
        document.getElementById('dq-selected-fields').innerHTML=ids.length?`<div class="border rounded p-3"><div class="mb-2"><strong>Column Display Order &amp; Labels</strong><div class="text-secondary small">This exact order is used by Preview, XLSX and PDF.</div></div><div class="vstack gap-2">${ids.map((id,i)=>`<div class="row g-2 align-items-center border rounded p-2 dq-selected-row" data-id="${esc(id)}"><div class="col-md-4"><span class="badge bg-secondary-lt me-2">${i+1}</span><strong>${esc(byId[id].label)}</strong><div class="text-secondary small ms-4">${esc(byId[id].module)}</div></div><div class="col-md-5"><input class="form-control form-control-sm dq-label" data-id="${esc(id)}" value="${esc(labelOverrides[id]??byId[id].label)}" placeholder="Display column name"></div><div class="col-md-3 text-end"><div class="btn-group btn-group-sm"><button type="button" class="btn btn-outline-secondary dq-move-left" ${i===0?'disabled':''} title="Move left">←</button><button type="button" class="btn btn-outline-secondary dq-move-right" ${i===ids.length-1?'disabled':''} title="Move right">→</button><button type="button" class="btn btn-outline-danger dq-selected-remove" title="Remove">×</button></div></div></div>`).join('')}</div></div>`:'<div class="text-secondary">No output fields selected.</div>';
        document.querySelectorAll('.dq-label').forEach(e=>e.oninput=()=>labelOverrides[e.dataset.id]=e.value);
        document.querySelectorAll('.dq-selected-row').forEach(row=>{const id=row.dataset.id;row.querySelector('.dq-move-left').onclick=()=>moveSelected(id,-1);row.querySelector('.dq-move-right').onclick=()=>moveSelected(id,1);row.querySelector('.dq-selected-remove').onclick=()=>removeSelected(id);});
    }

    function valueHtml(f,op,value=null,value2=null){
        if(!f||['is_blank','is_not_blank'].includes(op)) return '<input class="form-control form-control-sm dq-c-value" disabled placeholder="No value needed">';
        const inputType=f.type==='date'?'date':(f.type==='number'?'number':'text'), step=f.type==='number'?' step="any"':'';
        if(op==='between') return `<div class="d-flex gap-1"><input type="${inputType}"${step} class="form-control form-control-sm dq-c-value" value="${esc(value??'')}" placeholder="From"><input type="${inputType}"${step} class="form-control form-control-sm dq-c-value2" value="${esc(value2??'')}" placeholder="To"></div>`;
        if(f.options&&Object.keys(f.options).length){
            const values=Array.isArray(value)?value.map(String):String(value??'').split(',').map(v=>v.trim()).filter(Boolean);
            if(op==='in') return `<select class="form-select form-select-sm dq-c-value" multiple size="${Math.min(5,Math.max(2,Object.keys(f.options).length))}">${Object.entries(f.options).map(([v,l])=>`<option value="${esc(v)}" ${values.includes(String(v))?'selected':''}>${esc(l)}</option>`).join('')}</select><div class="form-hint">Ctrl/Command-click to select multiple values.</div>`;
            return `<select class="form-select form-select-sm dq-c-value"><option value="">Choose…</option>${Object.entries(f.options).map(([v,l])=>`<option value="${esc(v)}" ${String(value??'')===String(v)?'selected':''}>${esc(l)}</option>`).join('')}</select>`;
        }
        return `<input type="${inputType}"${step} class="form-control form-control-sm dq-c-value" value="${esc(Array.isArray(value)?value.join(', '):(value??''))}" placeholder="${op==='in'?'Comma-separated values':'Value'}">`;
    }
    function conditionRow(initial={}){ const row=document.createElement('div'); row.className='row g-2 align-items-center dq-condition'; row.innerHTML=`<div class="col-md-5"><select class="form-select form-select-sm dq-c-field"><option value="">Choose field…</option>${fieldOptions()}</select></div><div class="col-md-3"><select class="form-select form-select-sm dq-c-op" disabled><option>Operator</option></select></div><div class="col-md-3 dq-c-values"><input class="form-control form-control-sm" disabled placeholder="Value"></div><div class="col-md-1 text-end"><button type="button" class="btn btn-sm btn-outline-danger dq-remove">×</button></div>`; const fs=row.querySelector('.dq-c-field'),os=row.querySelector('.dq-c-op'),vs=row.querySelector('.dq-c-values'); fs.onchange=()=>{const f=byId[fs.value];os.disabled=!f;os.innerHTML='<option value="">Operator</option>'+(f?f.operators.map(o=>`<option value="${o}">${opLabel(o)}</option>`).join(''):'');vs.innerHTML=valueHtml(f,'');}; os.onchange=()=>vs.innerHTML=valueHtml(byId[fs.value],os.value); row.querySelector('.dq-remove').onclick=()=>row.remove(); if(initial.field&&byId[initial.field]){fs.value=initial.field;fs.onchange();os.value=initial.operator||'';vs.innerHTML=valueHtml(byId[initial.field],initial.operator||'',initial.value,initial.value2);} return row; }
    function conditionGroup(initial={},isRoot=false){ const box=document.createElement('div'); box.className='dq-condition-group border rounded p-2 mb-2'; box.innerHTML=`<div class="d-flex align-items-center gap-2 mb-2"><span class="text-secondary small">Match</span><select class="form-select form-select-sm dq-g-boolean" style="width:90px"><option value="and">ALL</option><option value="or">ANY</option></select><span class="text-secondary small">of these rules</span><span class="ms-auto"></span><button type="button" class="btn btn-sm btn-ghost-primary dq-add-rule">+ Rule</button><button type="button" class="btn btn-sm btn-ghost-primary dq-add-subgroup">+ Group</button>${isRoot?'':'<button type="button" class="btn btn-sm btn-outline-danger dq-remove-group">×</button>'}</div><div class="dq-group-rules vstack gap-2"></div>`; const rules=box.querySelector('.dq-group-rules');box.querySelector('.dq-g-boolean').value=initial.boolean==='or'?'or':'and'; box.querySelector('.dq-add-rule').onclick=()=>rules.appendChild(conditionRow()); box.querySelector('.dq-add-subgroup').onclick=()=>rules.appendChild(conditionGroup()); box.querySelector('.dq-remove-group')?.addEventListener('click',()=>box.remove());(initial.rules||[]).forEach(rule=>rules.appendChild(rule.rules?conditionGroup(rule):conditionRow(rule)));return box; }
    function readGroup(box){ return {boolean:box.querySelector(':scope > .d-flex .dq-g-boolean').value,rules:[...box.querySelector(':scope > .dq-group-rules').children].map(el=>{if(el.classList.contains('dq-condition-group'))return readGroup(el);const valueEl=el.querySelector('.dq-c-value');const value=valueEl?.multiple?[...valueEl.selectedOptions].map(o=>o.value):(valueEl?.value??null);return {field:el.querySelector('.dq-c-field').value,operator:el.querySelector('.dq-c-op').value,value,value2:el.querySelector('.dq-c-value2')?.value??null};}).filter(r=>r.rules?.length||(r.field&&r.operator))}; }

    function addSort(initial={}){ const row=document.createElement('div'); row.className='row g-2 dq-sort'; row.innerHTML=`<div class="col-md-1"><span class="badge bg-secondary-lt dq-sort-priority"></span></div><div class="col-md-7"><select class="form-select form-select-sm dq-s-field"><option value="">Choose sortable field…</option>${fieldOptions(f=>f.sortable)}</select></div><div class="col-md-3"><select class="form-select form-select-sm dq-s-dir"><option value="asc">Ascending</option><option value="desc">Descending</option></select></div><div class="col-md-1"><button class="btn btn-sm btn-outline-danger dq-remove" type="button">×</button></div>`; row.querySelector('.dq-s-field').value=initial.field||'';row.querySelector('.dq-s-dir').value=initial.direction==='desc'?'desc':'asc';row.querySelector('.dq-remove').onclick=()=>{row.remove();renumberSorts();}; document.getElementById('dq-sorts').appendChild(row);renumberSorts(); }
    function renumberSorts(){document.querySelectorAll('.dq-sort-priority').forEach((e,i)=>e.textContent=i+1);}
    function addGroupField(value=''){ const row=document.createElement('div'); row.className='row g-1 dq-group-field'; row.innerHTML=`<div class="col-10"><select class="form-select form-select-sm dq-group-select"><option value="">Choose group field…</option>${fieldOptions(f=>f.groupable)}</select></div><div class="col-2"><button class="btn btn-sm btn-outline-danger dq-remove" type="button">×</button></div>`; row.querySelector('.dq-group-select').value=value;row.querySelector('.dq-remove').onclick=()=>row.remove();document.getElementById('dq-groups').appendChild(row); }
    function addAggregate(initial={}){ const row=document.createElement('div');row.className='row g-1 dq-aggregate';row.innerHTML=`<div class="col-5"><select class="form-select form-select-sm dq-a-field"><option value="">Field…</option>${fieldOptions(f=>f.aggregates?.length)}</select></div><div class="col-3"><select class="form-select form-select-sm dq-a-function" disabled><option value="">Function</option></select></div><div class="col-3"><input class="form-control form-control-sm dq-a-label" placeholder="Column title" value="${esc(initial.label||'')}"></div><div class="col-1"><button class="btn btn-sm btn-outline-danger dq-remove" type="button">×</button></div>`;const f=row.querySelector('.dq-a-field'),fn=row.querySelector('.dq-a-function');f.onchange=()=>{const m=byId[f.value];fn.disabled=!m;fn.innerHTML='<option value="">Function</option>'+(m?m.aggregates.map(a=>`<option value="${a}">${a.replace('_',' ').toUpperCase()}</option>`).join(''):'');};if(initial.field){f.value=initial.field;f.onchange();fn.value=initial.function||'';}row.querySelector('.dq-remove').onclick=()=>row.remove();document.getElementById('dq-aggregates').appendChild(row); }
    function definition(){ const labels={...labelOverrides};document.querySelectorAll('.dq-label').forEach(e=>{labels[e.dataset.id]=e.value;labelOverrides[e.dataset.id]=e.value;}); const summary=document.getElementById('dq-mode').value==='summary'; return {mode:summary?'summary':'detail',fields:[...selected],conditions:readGroup(document.querySelector('#dq-conditions > .dq-condition-group')),sorts:[...document.querySelectorAll('.dq-sort')].map(r=>({field:r.querySelector('.dq-s-field').value,direction:r.querySelector('.dq-s-dir').value})).filter(x=>x.field),groups:summary?[...document.querySelectorAll('.dq-group-select')].map(e=>e.value).filter(Boolean):[],aggregates:summary?[...document.querySelectorAll('.dq-aggregate')].map(r=>({field:r.querySelector('.dq-a-field').value,function:r.querySelector('.dq-a-function').value,label:r.querySelector('.dq-a-label').value})).filter(x=>x.field&&x.function):[],labels,preview_size:Number(document.getElementById('dq-preview-size').value),report_title:document.getElementById('dq-title').value,show_serial:document.getElementById('dq-show-serial').checked,show_page_number:document.getElementById('dq-show-page').checked,show_timestamp:document.getElementById('dq-show-time').checked}; }
    function applyDefinition(def={}){selected.clear();Object.keys(labelOverrides).forEach(k=>delete labelOverrides[k]);(def.fields||[]).forEach(id=>{if(byId[id])selected.add(id)});renderFieldList(document.getElementById('dq-field-search').value);renderSelected(def.labels||{});renderSelectedChips();document.getElementById('dq-mode').value=def.mode==='summary'?'summary':'detail';document.getElementById('dq-summary-config').style.display=def.mode==='summary'?'block':'none';document.getElementById('dq-title').value=def.report_title||'Dynamic Query Report';document.getElementById('dq-preview-size').value=String(def.preview_size||root.dataset.defaultPreview);document.getElementById('dq-show-serial').checked=def.show_serial!==false;document.getElementById('dq-show-page').checked=def.show_page_number!==false;document.getElementById('dq-show-time').checked=def.show_timestamp!==false;document.getElementById('dq-conditions').innerHTML='';const rootGroup=conditionGroup(def.conditions||{boolean:'and',rules:[]},true);document.getElementById('dq-conditions').appendChild(rootGroup);if(!rootGroup.querySelector('.dq-group-rules').children.length)rootGroup.querySelector('.dq-group-rules').appendChild(conditionRow());document.getElementById('dq-sorts').innerHTML='';(def.sorts||[]).forEach(addSort);if(!(def.sorts||[]).length)addSort();document.getElementById('dq-groups').innerHTML='';(def.groups||[]).forEach(addGroupField);if(!(def.groups||[]).length)addGroupField();document.getElementById('dq-aggregates').innerHTML='';(def.aggregates||[]).forEach(addAggregate);if(!(def.aggregates||[]).length)addAggregate();}

    async function saveReport(asNew=false){const status=document.getElementById('dq-save-status'),name=document.getElementById('dq-save-name').value.trim();if(!name){status.className='small text-danger mt-2';status.textContent='Enter a saved report name first.';return;}status.className='small text-secondary mt-2';status.textContent='Saving semantic report definition…';const payload={id:asNew?null:currentSavedReportId,name,description:document.getElementById('dq-save-description').value,definition:definition()};const res=await fetch(root.dataset.saveUrl,{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf()},body:JSON.stringify(payload)}),data=await res.json();if(!res.ok){status.className='small text-danger mt-2';status.textContent=Object.values(data.errors||{}).flat().join(' ')||data.message||'Save failed.';return;}currentSavedReportId=data.report.id;savedReports=data.saved_reports||savedReports;document.getElementById('dq-save-name').value=data.report.name;document.getElementById('dq-save-description').value=data.report.description||'';renderSavedReports();renderHistory();status.className='small text-success mt-2';status.textContent=`Saved as version ${data.report.version}.`;}
    async function loadSavedReport(id){if(!id)return;const status=document.getElementById('dq-save-status');const res=await fetch(`${root.dataset.savedBaseUrl}/${id}`,{headers:{Accept:'application/json'}}),data=await res.json();if(!res.ok){status.className='small text-danger mt-2';status.textContent=data.message||'Unable to load saved report.';return;}currentSavedReportId=data.id;document.getElementById('dq-save-name').value=data.name;document.getElementById('dq-save-description').value=data.description||'';applyDefinition(data.definition||{});renderSavedReports();await refreshHistory();status.className='small text-success mt-2';status.textContent=`Loaded ${data.name} · version ${data.version}.`;}

    document.getElementById('dq-field-search').oninput=e=>renderFieldList(e.target.value);document.getElementById('dq-clear-fields').onclick=()=>{selected.clear();Object.keys(labelOverrides).forEach(k=>delete labelOverrides[k]);renderFieldList(document.getElementById('dq-field-search').value);renderSelected();renderSelectedChips();}; document.getElementById('dq-add-condition').onclick=()=>document.querySelector('#dq-conditions > .dq-condition-group > .dq-group-rules').appendChild(conditionRow()); document.getElementById('dq-add-sort').onclick=()=>addSort(); document.getElementById('dq-add-group').onclick=()=>addGroupField(); document.getElementById('dq-add-aggregate').onclick=()=>addAggregate(); document.getElementById('dq-mode').onchange=e=>document.getElementById('dq-summary-config').style.display=e.target.value==='summary'?'block':'none';
    document.getElementById('dq-saved-report').onchange=e=>{currentSavedReportId=e.target.value?Number(e.target.value):null;document.getElementById('dq-delete-report').disabled=!currentSavedReportId;renderHistory();};document.getElementById('dq-load-report').onclick=()=>loadSavedReport(Number(document.getElementById('dq-saved-report').value));document.getElementById('dq-save-report').onclick=()=>saveReport(false);document.getElementById('dq-save-as').onclick=()=>saveReport(true);document.getElementById('dq-delete-report').onclick=async()=>{if(!currentSavedReportId||!confirm('Delete this saved report and its execution history?'))return;const res=await fetch(`${root.dataset.savedBaseUrl}/${currentSavedReportId}`,{method:'DELETE',headers:{Accept:'application/json','X-CSRF-TOKEN':csrf()}}),data=await res.json();if(!res.ok)return;currentSavedReportId=null;savedReports=data.saved_reports||[];document.getElementById('dq-save-name').value='';document.getElementById('dq-save-description').value='';renderSavedReports();await refreshHistory();};
    async function queueExport(format){const isPdf=format==='PDF',b=document.getElementById(isPdf?'dq-export-pdf':'dq-export-xlsx'),status=document.getElementById('dq-export-status');b.disabled=true;status.className='small text-secondary mt-2';status.textContent=`Queuing ${format} report generation…`;try{const def=definition(),url=isPdf?root.dataset.exportPdfUrl:root.dataset.exportUrl,res=await fetch(url,{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf()},body:JSON.stringify({...def,saved_report_id:currentSavedReportId})}),data=await res.json();if(!res.ok)throw new Error(Object.values(data.errors||{}).flat().join(' ')||data.message||`Unable to queue ${format} export.`);status.textContent=`${format} queued. Generating report…`;const poll=async()=>{const r=await fetch(data.status_url,{headers:{Accept:'application/json'}}),d=await r.json();if(!r.ok)throw new Error(d.message||'Unable to read export status.');if(d.finished&&!d.download_url){throw new Error(d.failure_message?`${format} failed: ${d.failure_message}`:(d.progress_message||`${format} generation failed.`));}status.textContent=`${d.progress_message||'Generating report…'} ${d.progress_percent??0}%`;if(d.download_url){status.innerHTML=`${esc(d.progress_message||format+' ready.')} <a class="ms-2" href="${esc(d.download_url)}">Download ${format}</a>`;b.disabled=false;if(currentSavedReportId)await refreshHistory();return;}setTimeout(poll,1200);};await poll();}catch(e){status.className='small text-danger mt-2';status.textContent=e.message;b.disabled=false;if(currentSavedReportId)await refreshHistory();}}
    document.getElementById('dq-export-xlsx').onclick=()=>queueExport('XLSX');
    document.getElementById('dq-export-pdf').onclick=()=>queueExport('PDF');
    document.getElementById('dq-clear-preview').onclick=()=>{document.getElementById('dq-count').textContent='Not calculated';document.getElementById('dq-message').className='text-secondary';document.getElementById('dq-message').textContent='Preview cleared. Report configuration is unchanged.';document.getElementById('dq-preview-table').innerHTML='';};
    document.getElementById('dq-preview').onclick=async()=>{const b=document.getElementById('dq-preview'),msg=document.getElementById('dq-message'),table=document.getElementById('dq-preview-table');b.disabled=true;msg.className='text-secondary';msg.textContent='Running controlled preview…';table.innerHTML='';try{const def=definition(),res=await fetch(root.dataset.previewUrl,{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf()},body:JSON.stringify({...def,saved_report_id:currentSavedReportId})}),data=await res.json();if(!res.ok)throw new Error(Object.values(data.errors||{}).flat().join(' ')||data.message||'Preview failed.');document.getElementById('dq-count').textContent=`${Number(data.count).toLocaleString()} matching`;msg.textContent=(data.warnings?.length?data.warnings.join(' '):'Preview uses current authoritative module data.')+` · ${data.duration_ms??0} ms`;table.innerHTML=`<table class="table table-vcenter table-sm"><thead><tr>${def.show_serial?'<th>#</th>':''}${data.columns.map(c=>`<th>${esc(c.label)}</th>`).join('')}</tr></thead><tbody>${data.rows.map((r,i)=>`<tr>${def.show_serial?`<td>${i+1}</td>`:''}${data.columns.map(c=>`<td>${esc(r[c.id]??'—')}</td>`).join('')}</tr>`).join('')||`<tr><td colspan="${data.columns.length+(def.show_serial?1:0)}" class="text-secondary text-center">No matching records.</td></tr>`}</tbody></table>`;if(currentSavedReportId)await refreshHistory();}catch(e){msg.className='text-danger';msg.textContent=e.message;}finally{b.disabled=false;}};

    applyDefinition({preview_size:Number(root.dataset.defaultPreview),conditions:{boolean:'and',rules:[]},sorts:[],groups:[],aggregates:[]});renderSavedReports();renderHistory();
});
</script>
@endsection
