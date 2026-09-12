@php
 $selectedCadre = old('cadre_code', $entry->cadre_code ?? '');
 $selectedSub = old('sub_cadre_code', $entry->sub_cadre_code ?? '');
 $selectedBachelors = collect(old('bachelor_subject_codes', isset($entry) ? $entry->bachelorSubjects->pluck('subject_code')->map(fn($v)=>(string)$v)->all() : []))->map(fn($v)=>(string)$v)->all();
 $selectedPrs = collect(old('prs_codes', isset($entry) ? $entry->prsSubjects->pluck('prs_code')->map(fn($v)=>(string)$v)->all() : []))->map(fn($v)=>(string)$v)->all();
@endphp
<div class="row g-3">
 <div class="col-md-2"><label class="form-label required">Cadre serial</label><input type="number" min="1" name="cadre_serial" class="form-control" value="{{ old('cadre_serial',$entry->cadre_serial ?? '') }}" required></div>
 <div class="col-md-2"><label class="form-label">Sub serial</label><input type="number" min="1" name="sub_serial" class="form-control" value="{{ old('sub_serial',$entry->sub_serial ?? '') }}"></div>
 <div class="col-md-4"><label class="form-label required">Parent Cadre</label><select id="cadre_code" name="cadre_code" class="form-select" required><option value="">Select cadre</option>@foreach($cadres as $cadre)<option value="{{ $cadre->cadre_code }}" data-type="{{ $cadre->cadre_type->value }}" data-name="{{ $cadre->cadre_name }}" data-post="{{ $cadre->post_name }}" @selected((string)$selectedCadre===(string)$cadre->cadre_code)>{{ $cadre->cadre_code }} — {{ $cadre->cadre_name }}</option>@endforeach</select></div>
 <div class="col-md-4"><label class="form-label">Sub Cadre / Post Code</label><select id="sub_cadre_code" name="sub_cadre_code" class="form-select"><option value="">Use main cadre code</option>@foreach($subCadres as $sub)<option value="{{ $sub->sub_cadre_code }}" data-parent="{{ $sub->parentCadre?->cadre_code }}" data-post="{{ $sub->post_name }}" @selected((string)$selectedSub===(string)$sub->sub_cadre_code)>{{ $sub->sub_cadre_code }} — {{ $sub->post_name }}</option>@endforeach</select></div>
 <div class="col-md-3"><label class="form-label required">Post count</label><input type="number" min="1" name="post_count" class="form-control" value="{{ old('post_count',$entry->post_count ?? '') }}" required></div>
 <div class="col-md-3"><label class="form-label required">Status</label><select name="status" class="form-select"><option value="ACTIVE" @selected(strtoupper(old('status',$entry->status ?? 'ACTIVE'))==='ACTIVE')>ACTIVE</option><option value="INACTIVE" @selected(strtoupper(old('status',$entry->status ?? ''))==='INACTIVE')>INACTIVE</option></select></div>
 <div class="col-md-6"><div class="alert alert-secondary mb-0 py-2"><div class="small text-secondary">Auto-picked identity</div><div class="fw-semibold" id="resolved_identity">Select a Cadre code</div><div class="small" id="resolved_type"></div></div></div>
 <div class="col-md-6 eligibility-field"><label class="form-label">Bachelor subject codes</label><select name="bachelor_subject_codes[]" class="form-select" multiple size="9">@foreach($bachelorSubjects as $subject)<option value="{{ $subject->subject_code }}" @selected(in_array((string)$subject->subject_code,$selectedBachelors,true))>{{ $subject->subject_code }} — {{ $subject->subject_name }}</option>@endforeach</select><div class="form-hint">TT rows require one or more. Excel equivalent uses <code>|</code>.</div></div>
 <div class="col-md-6 eligibility-field"><label class="form-label">Post Related Subject (PRS) codes</label><select name="prs_codes[]" class="form-select" multiple size="9">@foreach($prsSubjects as $subject)<option value="{{ $subject->subject_code }}" @selected(in_array((string)$subject->subject_code,$selectedPrs,true))>{{ $subject->subject_code }} — {{ $subject->subject_name }}</option>@endforeach</select><div class="form-hint">Registration PRS remains authoritative downstream.</div></div>
 <div class="col-12"><label class="form-label">Note</label><textarea name="note" class="form-control" rows="3">{{ old('note',$entry->note ?? '') }}</textarea></div>
 <div class="col-md-3"><label class="form-label">Special Requirement</label><select id="special_requirement" name="special_requirement" class="form-select"><option value="0" @selected((string)old('special_requirement',(int)($entry->special_requirement ?? 0))==='0')>NO</option><option value="1" @selected((string)old('special_requirement',(int)($entry->special_requirement ?? 0))==='1')>YES</option></select><div class="form-hint">Optional UI-only metadata; not imported from Excel.</div></div>
 <div class="col-md-9"><label class="form-label">Special Requirement Comment</label><textarea id="special_requirement_comment" name="special_requirement_comment" class="form-control" rows="3" placeholder="Describe the qualification or condition to verify manually.">{{ old('special_requirement_comment',$entry->special_requirement_comment ?? '') }}</textarea><div class="form-hint">Required when Special Requirement is YES.</div></div>
 <div class="col-12"><label class="form-label required">{{ isset($entry) ? 'Reason for correction' : 'Reason / administrative note' }}</label><textarea name="correction_reason" class="form-control" rows="3" required>{{ old('correction_reason') }}</textarea><div class="form-hint">Every manual Circular change requires an auditable reason. A no-op update will not create a false audit event.</div></div>
</div>
@push('scripts')
<script>
(function(){
 const cadre=document.getElementById('cadre_code'), sub=document.getElementById('sub_cadre_code'), identity=document.getElementById('resolved_identity'), type=document.getElementById('resolved_type'), special=document.getElementById('special_requirement'), specialComment=document.getElementById('special_requirement_comment');
 function refresh(){
   const c=cadre.options[cadre.selectedIndex], code=cadre.value;
   [...sub.options].forEach((o,i)=>{if(i===0)return; o.hidden=!!code && o.dataset.parent!==code;});
   if(sub.value && sub.options[sub.selectedIndex]?.dataset.parent!==code) sub.value='';
   const s=sub.value ? sub.options[sub.selectedIndex] : null;
   identity.textContent=code ? (c.dataset.name+' — '+(s?.dataset.post || c.dataset.post || '')) : 'Select a Cadre code';
   type.textContent=code ? ('Cadre type: '+c.dataset.type+' · Effective code: '+(s?.value || code)) : '';
 }
 function refreshSpecial(){ if(!special||!specialComment)return; specialComment.required=special.value==='1'; }
 cadre.addEventListener('change',refresh); sub.addEventListener('change',refresh); if(special)special.addEventListener('change',refreshSpecial); refresh(); refreshSpecial();
})();
</script>
@endpush
