<style>
body{font-family:sans-serif;font-size:8.6pt;color:#222}
h1{font-size:15pt;margin:0 0 2mm}.muted{color:#667085}.small{font-size:7.8pt}
.section{border:1px solid #d7dde5;margin:3mm 0;padding:2.5mm}
h2{font-size:11pt;margin:0 0 2mm}h3{font-size:9.5pt;margin:1.5mm 0}
table{width:100%;border-collapse:collapse}th,td{border:1px solid #d7dde5;padding:1.45mm;text-align:left;vertical-align:top}th{background:#f6f8fa}
.ok{color:#137333;font-weight:bold}.bad{color:#b42318;font-weight:bold}.code{font-family:monospace;font-size:7.5pt}
.grid{width:100%;border-collapse:separate;border-spacing:2mm;margin:0 -2mm}
.grid>tbody>tr>td{width:50%;border:0;padding:0 0 2mm 2mm;vertical-align:top}
.card{border:1px solid #d7dce2}.card-title{background:#f5f7fa;font-weight:bold;padding:1.7mm 2mm;border-bottom:1px solid #d7dce2}
.info td{border:0;border-bottom:1px solid #eceff3;padding:1.3mm 1.8mm}.info tr:last-child td{border-bottom:0}.label{width:42%;color:#667085}
.choice th,.choice td{text-align:center;white-space:nowrap}.choice th:first-child{text-align:left}
</style>

<h1>Individual Finalized Merit</h1>
<div class="muted">{{ $exam?->name ?? 'BCS Examination' }} · REG {{ $result->reg }} · Version {{ $result->processing_version }} · Generated {{ $generatedAt->format('Y-m-d H:i:s') }}</div>

<div class="section">
<h2>Upstream Finalized Data</h2>
<table class="grid">
<tr><td><div class="card"><div class="card-title">Registration</div><table class="info">
<tr><td class="label">Name</td><td>{{ $registration->name }}</td></tr>
<tr><td class="label">REG</td><td>{{ $registration->reg }}</td></tr>
<tr><td class="label">USER</td><td>{{ $registration->user_id }}</td></tr>
<tr><td class="label">Category</td><td>{{ $registration->cadre_category ? ($registration->cadre_category->value.' - '.$registration->cadre_category->code()) : '—' }}</td></tr>
<tr><td class="label">Bachelor Subject</td><td>{{ $bachelorSubjectDisplay }}</td></tr>
<tr><td class="label">Post Related Subject (PRS)</td><td>{{ $postRelatedSubjectDisplay }}</td></tr>
<tr><td class="label">Birth Date</td><td>{{ $registration->birth_date?->format('Y-m-d') ?? '—' }}</td></tr>
<tr><td class="label">Graduation Year</td><td>{{ $registration->graduation_year ?? '—' }}</td></tr>
</table></div></td>
<td><div class="card"><div class="card-title">Preliminary / Viva</div><table class="info">
<tr><td class="label">Preliminary Mark</td><td>{{ $preliminary?->mark ?? '—' }}</td></tr>
<tr><td class="label">Preliminary Result</td><td>{{ strtoupper((string)($preliminary?->result_status?->value ?? $preliminary?->result_status ?? '—')) }}</td></tr>
<tr><td class="label">Viva Attendance</td><td>{{ strtoupper((string)$viva->attendance_status) }}</td></tr>
<tr><td class="label">Viva Mark</td><td>{{ $viva->mark }}</td></tr>
<tr><td class="label">Viva Result</td><td>{{ strtoupper((string)($viva->viva_result_status?->value ?? $viva->viva_result_status ?? '—')) }}</td></tr>
</table></div></td></tr>
<tr><td colspan="2"><div class="card"><div class="card-title">Written</div><table class="info">
<tr><td class="label">Qualified Track</td><td>{{ strtoupper((string)($written->written_qualified_track?->value ?? $written->written_qualified_track ?? '—')) }}</td></tr>
<tr><td class="label">General Counted</td><td>{{ $written->general_counted_total ?? '—' }} · {{ strtoupper((string)($written->general_result_status?->value ?? $written->general_result_status ?? '—')) }}</td></tr>
<tr><td class="label">Technical Counted</td><td>{{ $written->technical_counted_total ?? '—' }} · {{ strtoupper((string)($written->technical_result_status?->value ?? $written->technical_result_status ?? '—')) }}</td></tr>
</table></div></td></tr>
</table>
</div>

<div class="section">
<h2>Finalized Tabulation Ranking Inputs</h2>
<table>
<tr><th>Track</th><th>Preliminary</th><th>General Written</th><th>Technical Written</th><th>Viva</th><th>General Grand</th><th>Technical Grand</th><th>G/T Merit Eligible</th></tr>
<tr><td>{{ strtoupper((string)$tabulation->written_qualified_track) }}</td><td>{{ $tabulation->preliminary_mark ?? '—' }}</td><td>{{ $tabulation->general_written_total ?? '—' }}</td><td>{{ $tabulation->technical_written_total ?? '—' }}</td><td>{{ $tabulation->viva_mark }}</td><td>{{ $tabulation->generalGrandTotalDisplay() }}</td><td>{{ $tabulation->technicalGrandTotalDisplay() }}</td><td>{{ $tabulation->general_merit_eligible?'YES':'NO' }} / {{ $tabulation->technical_merit_eligible?'YES':'NO' }}</td></tr>
</table>
</div>

<div class="section">
<h2>Finalized Choice Validation</h2>
<div class="small muted">Original Choice</div>
<table class="choice"><tr><th>Order</th>@foreach($originalChoiceCodes as $i=>$code)<th>{{ $i+1 }}</th>@endforeach</tr><tr><th>Code</th>@foreach($originalChoiceCodes as $code)<td>{{ $code }}</td>@endforeach</tr><tr><th>ABBR</th>@foreach($originalChoiceAbbrs as $abbr)<td>{{ $abbr }}</td>@endforeach</tr></table>
<div class="small muted" style="margin-top:2mm">Validated Choice</div>
<table class="choice"><tr><th>Order</th>@foreach($validatedChoiceCodes as $i=>$code)<th>{{ $i+1 }}</th>@endforeach</tr><tr><th>Code</th>@foreach($validatedChoiceCodes as $code)<td>{{ $code }}</td>@endforeach</tr><tr><th>ABBR</th>@foreach($validatedChoiceAbbrs as $abbr)<td>{{ $abbr }}</td>@endforeach</tr></table>
</div>

<div class="section">
<h2>Merit Source Authority</h2>
<table><tr><th>Source</th><th>Version / Run</th><th>Dataset Hash</th></tr>
<tr><td>Circular</td><td>v{{ data_get($result->run?->source_snapshot,'circular.version','—') }}</td><td class="code">{{ data_get($result->run?->source_snapshot,'circular.dataset_hash','—') }}</td></tr>
<tr><td>Tabulation</td><td>Run #{{ data_get($result->run?->source_snapshot,'tabulation.processing_run_id','—') }} / v{{ data_get($result->run?->source_snapshot,'tabulation.processing_version','—') }}</td><td class="code">{{ data_get($result->run?->source_snapshot,'tabulation.dataset_hash','—') }}</td></tr>
<tr><td>Choice Validation</td><td>v{{ data_get($result->run?->source_snapshot,'choice_validation.validation_version','—') }}</td><td class="code">{{ data_get($result->run?->source_snapshot,'choice_validation.dataset_hash','—') }}</td></tr>
</table>
</div>

<div class="section">
<h2>Generated Merit Ranking</h2>
<table><tr><th>Common Merit</th><th>General Merit</th><th>Technical Merit</th><th>all_merit_tech</th><th>Status</th></tr>
<tr><td>{{ $result->common_merit_position ?? '—' }}</td><td>{{ $result->general_merit_position ?? '—' }}</td><td>{{ $result->technical_merit_position ?? '—' }}</td><td class="code">{{ \App\Models\MeritResult::allMeritTechJson($result->all_merit_tech) }}</td><td>{{ $result->status_reason ?? 'MERIT_RANKED' }}</td></tr></table>
</div>

<div class="section">
<h2>Cadre-wise Merit Positions</h2>
<table><tr><th>Cadre</th><th>Type</th><th>Cadre Merit</th><th>Source Merit</th><th>Choice Position</th><th>Qualification Basis</th></tr>
@forelse($result->cadreRanks as $rank)
<tr><td>{{ $rank->cadre_code }} ({{ $rank->cadre_abbr }})</td><td>{{ $rank->cadre_type }}</td><td>{{ $rank->cadre_merit_position }}</td><td>{{ $rank->source_merit_position }}</td><td>{{ $rank->choice_position }}</td><td>{{ $rank->qualification_basis }}</td></tr>
@empty
<tr><td colspan="6">No cadre-wise merit position generated.</td></tr>
@endforelse
</table>
</div>
