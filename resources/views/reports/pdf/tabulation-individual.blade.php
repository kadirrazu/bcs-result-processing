<style>
body{font-family:sans-serif;font-size:9.5pt;color:#222}
h1{font-size:16pt;margin:0 0 3mm}.muted{color:#667085}.small{font-size:8.5pt}
.section{border:1px solid #d7dde5;margin:4mm 0;padding:3mm}
h2{font-size:12pt;margin:0 0 2mm}h3{font-size:10.5pt;margin:2mm 0}
table{width:100%;border-collapse:collapse}th,td{border:1px solid #d7dde5;padding:1.8mm;text-align:left;vertical-align:top}th{background:#f6f8fa}
.ok{color:#137333;font-weight:bold}.bad{color:#b42318;font-weight:bold}
.upstream-grid{width:100%;border-collapse:separate;border-spacing:2.5mm;margin:0 -2.5mm 2mm}
.upstream-grid>tbody>tr>td{width:50%;vertical-align:top;border:0;padding:0 0 2.5mm 2.5mm}
.info-card{border:1px solid #d7dce2}
.info-card-title{background:#f5f7fa;font-weight:bold;padding:2mm 2.4mm;border-bottom:1px solid #d7dce2}
.info-row{width:100%;border-collapse:collapse}
.info-row td{padding:1.6mm 2.2mm;border:0;border-bottom:1px solid #eceff3}
.info-row tr:last-child td{border-bottom:0}
.info-label{width:50%;color:#667085}.info-value{width:50%}
.section-subtitle{color:#667085;font-size:8.5pt;margin-bottom:2mm}
</style>

<h1>Individual Finalized Tabulation</h1>
<div class="muted">{{ $exam?->name ?? 'BCS Examination' }} · REG {{ $result->reg }} · Version {{ $result->processing_version }} · Generated {{ $generatedAt->format('Y-m-d H:i:s') }}</div>

<div class="section">
    <h2>Upstream Finalized Data</h2>
    <div class="section-subtitle">Authoritative values read from the finalized upstream modules.</div>

    <table class="upstream-grid">
        <tr>
            <td>
                <div class="info-card">
                    <div class="info-card-title">Registration</div>
                    <table class="info-row">
                        <tr><td class="info-label">Name</td><td class="info-value">{{ $registration->name }}</td></tr>
                        <tr><td class="info-label">Reg</td><td class="info-value">{{ $registration->reg }}</td></tr>
                        <tr><td class="info-label">User</td><td class="info-value">{{ $registration->user_id }}</td></tr>
                        <tr><td class="info-label">Registration Category</td><td class="info-value">{{ $registration->cadre_category ? ($registration->cadre_category->value.' - '.$registration->cadre_category->code()) : '—' }}</td></tr>
                    </table>
                </div>
            </td>
            <td>
                <div class="info-card">
                    <div class="info-card-title">Preliminary</div>
                    <table class="info-row">
                        <tr><td class="info-label">Mark</td><td class="info-value">{{ $preliminary?->mark ?? '—' }}</td></tr>
                        <tr><td class="info-label">Result</td><td class="info-value">
                            @php($pdfPrelimStatus = strtoupper((string) ($preliminary?->result_status?->value ?? $preliminary?->result_status ?? '')))
                            @if($pdfPrelimStatus === 'PASS')<span class="ok">PASS</span>@elseif($pdfPrelimStatus === 'FAIL')<span class="bad">FAIL</span>@else{{ $pdfPrelimStatus ?: '—' }}@endif
                        </td></tr>
                    </table>
                </div>
            </td>
        </tr>
        <tr>
            <td>
                <div class="info-card">
                    <div class="info-card-title">Written</div>
                    <table class="info-row">
                        <tr><td class="info-label">Qualified Track</td><td class="info-value">{{ strtoupper((string) ($written->written_qualified_track?->value ?? $written->written_qualified_track ?? $written->qualified_track ?? '')) }}</td></tr>
                        <tr><td class="info-label">General Counted</td><td class="info-value">
                            {{ $written->general_counted_total ?? '—' }}
                            @php($pdfGeneralWrittenStatus = strtoupper((string) ($written->general_result_status?->value ?? $written->general_result_status ?? '')))
                            @if($pdfGeneralWrittenStatus === 'PASS') <span class="ok">PASS</span>@elseif($pdfGeneralWrittenStatus === 'FAIL') <span class="bad">FAIL</span>@endif
                        </td></tr>
                        <tr><td class="info-label">Technical Counted</td><td class="info-value">
                            {{ $written->technical_counted_total ?? '—' }}
                            @php($pdfTechnicalWrittenStatus = strtoupper((string) ($written->technical_result_status?->value ?? $written->technical_result_status ?? '')))
                            @if($pdfTechnicalWrittenStatus === 'PASS') <span class="ok">PASS</span>@elseif($pdfTechnicalWrittenStatus === 'FAIL') <span class="bad">FAIL</span>@endif
                        </td></tr>
                    </table>
                </div>
            </td>
            <td>
                <div class="info-card">
                    <div class="info-card-title">Viva</div>
                    <table class="info-row">
                        <tr><td class="info-label">Attendance</td><td class="info-value">{{ strtoupper((string) $viva->attendance_status) }}</td></tr>
                        <tr><td class="info-label">Mark</td><td class="info-value">{{ $viva->mark }}</td></tr>
                        <tr><td class="info-label">Result</td><td class="info-value">
                            @php($pdfVivaStatus = strtoupper((string) ($viva->viva_result_status?->value ?? $viva->viva_result_status ?? '')))
                            @if($pdfVivaStatus === 'PASS')<span class="ok">PASS</span>@elseif($pdfVivaStatus === 'FAIL')<span class="bad">FAIL</span>@else{{ $pdfVivaStatus ?: '—' }}@endif
                        </td></tr>
                    </table>
                </div>
            </td>
        </tr>
    </table>
</div>

<div class="section">
    <h2>Source → Derived Verification</h2>
    <div class="muted small">Finalized upstream values compared with the values carried into Tabulation.</div>
    <table>
        <tr><th>Field</th><th>Upstream Finalized</th><th>Tabulation</th><th>Verification</th></tr>
        @foreach($verificationRows as $row)
            <tr><td>{{ $row['label'] }}</td><td>{{ $row['source'] ?? '—' }}</td><td>{{ $row['derived'] ?? '—' }}</td><td class="{{ $row['matches']?'ok':'bad' }}">{{ $row['matches']?'MATCH':'MISMATCH' }}</td></tr>
        @endforeach
    </table>
</div>

<div class="section">
    <h2>Derived Tabulation Data</h2>
    <table>
        <tr><th>General / Technical Written</th><td>{{ $result->general_written_total ?? '—' }} / {{ $result->technical_written_total ?? '—' }}</td></tr>
        <tr><th>Viva</th><td>{{ $result->viva_mark }}</td></tr>
        <tr><th>General / Technical Grand Total</th><td>{{ $result->generalGrandTotalDisplay() }} / {{ $result->technicalGrandTotalDisplay() }}</td></tr>
        <tr><th>General / Technical P/F</th><td>
            @php($pdfGeneralPf = strtoupper((string) $result->general_pf))
            @php($pdfTechnicalPf = strtoupper((string) $result->technical_pf))
            @if($pdfGeneralPf === 'PASS')<span class="ok">PASS</span>@elseif($pdfGeneralPf === 'FAIL')<span class="bad">FAIL</span>@else{{ $pdfGeneralPf }}@endif
            /
            @if($pdfTechnicalPf === 'PASS')<span class="ok">PASS</span>@elseif($pdfTechnicalPf === 'FAIL')<span class="bad">FAIL</span>@else{{ $pdfTechnicalPf }}@endif
        </td></tr>
        <tr><th>General / Technical Merit Eligible</th><td>{{ $result->general_merit_eligible?'YES':'NO' }} / {{ $result->technical_merit_eligible?'YES':'NO' }}</td></tr>
        <tr><th>Validation</th><td>{{ strtoupper($result->validation_status) }}</td></tr>
        <tr><th>Warnings</th><td>{{ implode(', ',(array)$result->review_warnings) ?: 'None' }}</td></tr>
        <tr><th>Validation Errors</th><td>{{ implode(', ',(array)$result->validation_errors) ?: 'None' }}</td></tr>
        <tr><th>Processed At</th><td>{{ $result->processed_at }}</td></tr>
    </table>
</div>
