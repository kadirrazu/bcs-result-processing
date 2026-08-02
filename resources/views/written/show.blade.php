@extends('layouts.app')
@section('title', 'Written Candidate')
@section('content')
@php
    $category = (int) ($registration?->cadre_category ?? $result->cadre_category ?? 0);
    $qualified = $result->written_qualified_track instanceof \BackedEnum ? $result->written_qualified_track->value : (string) ($result->written_qualified_track ?? '');
    $status = $result->status instanceof \BackedEnum ? $result->status->value : (string) $result->status;
    $validation = $result->validation_status instanceof \BackedEnum ? $result->validation_status->value : (string) $result->validation_status;
    $gStatus = $result->general_result_status instanceof \BackedEnum ? $result->general_result_status->value : (string) ($result->general_result_status ?? '');
    $tStatus = $result->technical_result_status instanceof \BackedEnum ? $result->technical_result_status->value : (string) ($result->technical_result_status ?? '');
    $effectiveCategory = match ($qualified) {
        'GG', 'GN' => 'GG',
        'TT', 'T' => 'TT',
        'GT' => 'GT',
        default => '—',
    };
@endphp
<div class="page-header d-print-none"><div class="container-xl"><div class="row g-2 align-items-center">
    <div class="col"><h2 class="page-title">Written Candidate</h2><div class="text-secondary">Approved source facts, derived Written result and immutable correction history.</div></div>
    <div class="col-auto ms-auto d-flex gap-2"><a href="{{ route('written.results.edit', $result) }}" class="btn btn-primary">Edit with Audit</a><a href="{{ route('written.results') }}" class="btn btn-outline-secondary">Back</a></div>
</div></div></div>
<div class="page-body"><div class="container-xl">
@if($state->is_stale)<div class="alert alert-warning"><strong>Written processing is stale.</strong> {{ $state->stale_reason }} Derived totals/PASS-FAIL must be regenerated before downstream use.</div>@endif
<div class="row row-cards mb-3">
    <div class="col-lg-5"><div class="card h-100"><div class="card-header"><h3 class="card-title">Candidate</h3></div><div class="card-body"><dl class="row mb-0">
        <dt class="col-5">REG</dt><dd class="col-7">{{ $result->reg }}</dd>
        <dt class="col-5">USER</dt><dd class="col-7">{{ $result->user_id }}</dd>
        <dt class="col-5">Name</dt><dd class="col-7">{{ $registration?->name ?? '—' }}</dd>
        <dt class="col-5">Original Category</dt><dd class="col-7"><strong>{{ $display['cadre_category'] }}</strong></dd>
        <dt class="col-5">Written Qualified Track</dt><dd class="col-7"><strong>{{ $qualified ?: '—' }}</strong></dd>
        <dt class="col-5">Effective Downstream</dt><dd class="col-7"><strong>{{ $effectiveCategory }}</strong></dd>
        <dt class="col-5">Written Status</dt><dd class="col-7">{{ strtoupper($status) }}</dd>
        <dt class="col-5">Validation</dt><dd class="col-7">{{ strtoupper($validation) }}</dd>
        <dt class="col-5">Registration PRS</dt><dd class="col-7"><strong>{{ $display['registration_prs'] }}</strong></dd>
        <dt class="col-5">Written Imported PRS</dt><dd class="col-7"><strong>{{ $display['written_prs'] }}</strong> @if($display['prs_mismatch'])<span class="badge bg-yellow-lt ms-1">Mismatch</span>@endif</dd>
        <dt class="col-5">Source Note</dt><dd class="col-7">{{ $result->data_source_note ?: '—' }}</dd>
        <dt class="col-5">Operator Comment</dt><dd class="col-7">{{ $result->comment ?: '—' }}</dd>
        <dt class="col-5">Bachelor Subject</dt><dd class="col-7">{{ $display['bachelor_subject'] }}</dd>
        <dt class="col-5">District</dt><dd class="col-7">{{ $display['district'] }}</dd>
        <dt class="col-5">Division</dt><dd class="col-7">{{ $display['division'] }}</dd>
        <dt class="col-5">University</dt><dd class="col-7">{{ $display['university'] }}</dd>
        <dt class="col-5">Gender</dt><dd class="col-7">{{ $display['gender'] }}</dd>
    </dl></div></div></div>
    <div class="col-lg-7"><div class="card h-100"><div class="card-header"><h3 class="card-title">Track Result</h3></div><div class="table-responsive"><table class="table card-table"><thead><tr><th>Track</th><th>Status</th><th>Actual</th><th>Counted</th><th>Fail Reasons</th></tr></thead><tbody>
        <tr><td>General</td><td>{{ $gStatus ? strtoupper($gStatus) : '—' }}</td><td>{{ $result->general_actual_total !== null ? number_format((float)$result->general_actual_total,2) : '—' }}</td><td>{{ $result->general_counted_total !== null ? number_format((float)$result->general_counted_total,2) : '—' }}</td><td class="small">{{ collect((array)$result->general_fail_reasons)->map(fn($x)=>is_array($x)?($x['code']??json_encode($x)):(string)$x)->implode(' | ') ?: '—' }}</td></tr>
        <tr><td>Technical</td><td>{{ $tStatus ? strtoupper($tStatus) : '—' }}</td><td>{{ $result->technical_actual_total !== null ? number_format((float)$result->technical_actual_total,2) : '—' }}</td><td>{{ $result->technical_counted_total !== null ? number_format((float)$result->technical_counted_total,2) : '—' }}</td><td class="small">{{ collect((array)$result->technical_fail_reasons)->map(fn($x)=>is_array($x)?($x['code']??json_encode($x)):(string)$x)->implode(' | ') ?: '—' }}</td></tr>
    </tbody></table></div></div></div>
</div>
<div class="card mb-3"><div class="card-header"><h3 class="card-title">Subject Marks</h3></div><div class="table-responsive"><table class="table table-vcenter card-table"><thead><tr><th>Subject</th><th>Raw</th><th>Actual</th><th>Counted</th><th>Attendance</th><th>Applicable</th><th>Crash</th><th>Warnings</th></tr></thead><tbody>
@foreach($result->marks as $mark)<tr class="{{ $mark->has_warning ? 'table-warning' : '' }}"><td><strong>{{ $mark->subject_code }}</strong></td><td>{{ $mark->raw_value ?? '—' }}</td><td>{{ $mark->actual_mark !== null ? number_format((float)$mark->actual_mark,2) : '—' }}</td><td>{{ $mark->counted_mark !== null ? number_format((float)$mark->counted_mark,2) : '—' }}</td><td>{{ $mark->attendance_status ? strtoupper($mark->attendance_status) : '—' }}</td><td>{{ $mark->is_applicable ? 'Yes' : 'No' }}</td><td>{{ $mark->paper_crashed ? 'YES' : 'No' }}</td><td class="small">{{ implode(' | ', (array)($mark->warning_codes ?? [])) ?: '—' }}</td></tr>@endforeach
</tbody></table></div></div>
<div class="card"><div class="card-header"><h3 class="card-title">Audit History</h3></div><div class="table-responsive"><table class="table table-sm card-table"><thead><tr><th>Time</th><th>Action</th><th>Actor</th><th>Reason</th><th>Before → After</th></tr></thead><tbody>
@forelse($audits as $audit)
    <tr>
        <td>{{ $audit->created_at?->format('d-m-Y h:i A') ?? '—' }}</td>
        <td>{{ $audit->action }}</td>
        <td>{{ $audit->actor_name ?: $audit->actor_id }}</td>
        <td>{{ $audit->reason ?: '—' }}</td>
        <td class="small">
            @forelse((array)($audit->changed_fields ?? []) as $field => $change)
                <div><strong>{{ $field }}</strong>: {{ is_scalar($change['before'] ?? null) ? ($change['before'] ?? 'NULL') : json_encode($change['before'] ?? null) }} → {{ is_scalar($change['after'] ?? null) ? ($change['after'] ?? 'NULL') : json_encode($change['after'] ?? null) }}</div>
            @empty
                —
            @endforelse
        </td>
    </tr>
@empty<tr><td colspan="5" class="text-center text-secondary py-3">No candidate-level Written audit events.</td></tr>@endforelse
</tbody></table></div></div>
</div></div>
@endsection
