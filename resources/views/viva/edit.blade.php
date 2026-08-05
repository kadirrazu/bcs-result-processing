@extends('layouts.app')
@section('title', 'Edit Viva Data')
@section('content')
@php
    $statusValue = $result->status instanceof \BackedEnum ? $result->status->value : (string) $result->status;
    $track = $result->written_qualified_track instanceof \BackedEnum ? $result->written_qualified_track->value : $result->written_qualified_track;
    $regQuota=[]; if((int)$registration->has_ff_quota>0)$regQuota[]='CFF'; if((int)$registration->has_em_quota>0)$regQuota[]='EM'; if((int)$registration->has_phc_quota>0)$regQuota[]='PHC';
@endphp
<div class="page-header d-print-none">
    <div class="container-xl">
        <div class="row g-2 align-items-center">
            <div class="col">
                <h2 class="page-title">Audited Viva Manual Correction</h2>
                <div class="text-secondary">{{ $result->reg }} · {{ $registration->name ?? '—' }} · Viva Code {{ $result->code }}</div>
            </div>
            <div class="col-auto ms-auto">
                <a href="{{ route('viva.candidates.index') }}" class="btn btn-outline-secondary">Back to Viva Data</a>
            </div>
        </div>
    </div>
</div>

<div class="page-body">
<div class="container-xl">
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('info'))<div class="alert alert-info">{{ session('info') }}</div>@endif

    <div class="alert alert-warning">
        <strong>Audit-controlled correction.</strong> A reason is mandatory. Result-affecting changes make Viva reconciliation outdated and it must be regenerated before continuing. Original imported raw values remain unchanged.
    </div>

    <div class="row row-cards mb-3">
        <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="text-secondary">Candidate</div><div class="fw-bold">{{ $result->reg }}</div><div>{{ $result->user_id }}</div></div></div></div>
        <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="text-secondary">Category / Written Track</div><div class="fw-bold">{{ $result->cadre_category }} / {{ $track }}</div></div></div></div>
        <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="text-secondary">Registration Quota</div><div class="fw-bold">{{ $regQuota ? implode(', ', $regQuota) : 'None' }}</div><div class="small text-secondary">Authoritative quota source</div></div></div></div>
        <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="text-secondary">Viva Code</div><div class="fw-bold">{{ $result->code }}</div><div class="small text-secondary">Read-only identity key</div></div></div></div>
    </div>

    <form method="post" action="{{ route('viva.candidates.update', $result) }}">
        @csrf
        @method('PUT')

        <div class="card mb-3">
            <div class="card-header"><h3 class="card-title">Effective Viva Data</h3></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label required">Viva date</label>
                        <input type="date" name="viva_date" class="form-control @error('viva_date') is-invalid @enderror" value="{{ old('viva_date', $result->viva_date?->format('Y-m-d')) }}" required>
                        @error('viva_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label required">Member ID</label>
                        <input name="member_id" class="form-control @error('member_id') is-invalid @enderror" value="{{ old('member_id', $result->member_id) }}" required>
                        @error('member_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label required">Mark / ABS</label>
                        <input name="mark" class="form-control @error('mark') is-invalid @enderror" value="{{ old('mark', $result->attendance_status === 'absent' ? 'ABS' : $result->mark) }}" required>
                        <div class="form-hint">Numeric mark or ABS. Maximum: {{ config('viva.full_mark', 100) }}.</div>
                        @error('mark')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label required">Viva status</label>
                        <select name="status" class="form-select @error('status') is-invalid @enderror" required>
                            @foreach(['active'=>'ACTIVE','cancelled'=>'CANCELLED','withheld'=>'WITHHELD','expelled'=>'EXPELLED'] as $value=>$label)
                                <option value="{{ $value }}" @selected(old('status', $statusValue) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <div class="form-hint">Only ACTIVE candidates remain in academic processing.</div>
                        @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12"><hr class="my-1"></div>

                    <div class="col-md-4">
                        <label class="form-label">Viva Board quota certification</label>
                        <label class="form-check"><input type="checkbox" name="viva_cff" value="1" class="form-check-input" @checked(old('viva_cff', $result->viva_cff))><span class="form-check-label">Viva CFF</span></label>
                        <label class="form-check"><input type="checkbox" name="viva_em" value="1" class="form-check-input" @checked(old('viva_em', $result->viva_em))><span class="form-check-label">Viva EM</span></label>
                        <label class="form-check"><input type="checkbox" name="viva_phc" value="1" class="form-check-input" @checked(old('viva_phc', $result->viva_phc))><span class="form-check-label">Viva PHC</span></label>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Source review flags</label>
                        <label class="form-check"><input type="checkbox" name="invalid_flag" value="1" class="form-check-input" @checked(old('invalid_flag', $result->invalid_flag))><span class="form-check-label">Invalid source flag</span></label>
                        <label class="form-check"><input type="checkbox" name="issue_flag" value="1" class="form-check-input" @checked(old('issue_flag', $result->issue_flag))><span class="form-check-label">Issue source flag</span></label>
                        <div class="form-hint">Flags are review information only; they do not automatically change candidate status.</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Operator comment</label>
                        <textarea name="comment" rows="4" class="form-control @error('comment') is-invalid @enderror">{{ old('comment', $result->comment) }}</textarea>
                        @error('comment')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12">
                        <label class="form-label required">Reason for correction</label>
                        <textarea name="reason" rows="3" class="form-control @error('reason') is-invalid @enderror" required placeholder="State the Commission decision, verified correction source, or other reason for this change.">{{ old('reason') }}</textarea>
                        @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
            <div class="card-footer text-end">
                <button class="btn btn-primary">Save Audited Correction</button>
            </div>
        </div>
    </form>

    <div class="card mb-3">
        <div class="card-header">
            <div>
                <h3 class="card-title">Original Imported Source</h3>
                <div class="text-secondary small">Read-only. These values are preserved even after manual correction.</div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead><tr><th>RAW DATE</th><th>RAW MARK</th><th>RAW CFF</th><th>RAW EM</th><th>RAW PHC</th><th>RAW INVALID</th><th>RAW ISSUE</th></tr></thead>
                <tbody><tr>
                    <td>{{ $result->raw_viva_date ?: '—' }}</td>
                    <td>{{ $result->raw_mark ?: '—' }}</td>
                    <td>{{ $result->raw_viva_cff ?: '—' }}</td>
                    <td>{{ $result->raw_viva_em ?: '—' }}</td>
                    <td>{{ $result->raw_viva_phc ?: '—' }}</td>
                    <td>{{ $result->raw_invalid_flag ?: '—' }}</td>
                    <td>{{ $result->raw_issue_flag ?: '—' }}</td>
                </tr></tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title">Manual Correction Audit Trail</h3>
                <div class="text-secondary small">Each correction preserves the exact value before and after the change.</div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th style="width: 170px;">TIME</th>
                        <th style="width: 150px;">OPERATOR</th>
                        <th>CHANGES</th>
                        <th style="width: 25%;">REASON</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($audits as $audit)
                    <tr>
                        <td class="text-nowrap">
                            {{ $audit->created_at?->timezone('Asia/Dhaka')->format('d M Y, h:i:s A') ?? '—' }}
                        </td>
                        <td>{{ $audit->actor_name ?: ('User #'.$audit->actor_id) }}</td>
                        <td>
                            @php
                                $changedFields = is_array($audit->changed_fields) ? $audit->changed_fields : [];
                                $beforeSnapshot = is_array($audit->before_snapshot) ? $audit->before_snapshot : [];
                                $afterSnapshot = is_array($audit->after_snapshot) ? $audit->after_snapshot : [];
                            @endphp

                            @if($changedFields === [])
                                <span class="text-secondary">No field-level change details available.</span>
                            @else
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered mb-0 align-middle">
                                        <thead>
                                            <tr>
                                                <th>FIELD</th>
                                                <th>OLD VALUE</th>
                                                <th>NEW VALUE</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        @foreach($changedFields as $field)
                                            <tr>
                                                <td class="fw-semibold">
                                                    {{ \App\Support\VivaAuditValuePresenter::label((string) $field) }}
                                                </td>
                                                <td class="text-break">
                                                    {{ \App\Support\VivaAuditValuePresenter::value(
                                                        (string) $field,
                                                        $beforeSnapshot[$field] ?? null
                                                    ) }}
                                                </td>
                                                <td class="text-break">
                                                    {{ \App\Support\VivaAuditValuePresenter::value(
                                                        (string) $field,
                                                        $afterSnapshot[$field] ?? null
                                                    ) }}
                                                </td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </td>
                        <td class="text-break">{{ $audit->reason }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center text-secondary py-4">
                            No manual correction has been recorded for this candidate.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>
@endsection
