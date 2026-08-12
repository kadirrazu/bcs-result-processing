@extends('layouts.app')
@section('title', 'Registration Details')
@section('content')
@php
    use App\Support\Registrations\RegistrationReferencePresenter;

    $genderName = $genders->firstWhere('code', $registration->sex_code)?->name;
    $divisionName = $divisions->firstWhere('code', $registration->division_code)?->name;
    $districtName = $districts->firstWhere('code', $registration->district_code)?->name;
    $universityName = $universities->firstWhere('code', $registration->university_code)?->name;
    $bachelorSubjectName = $bachelorSubjects->firstWhere('subject_code', $registration->bachelor_subject_code)?->subject_name;
    $relatedSubjectName = $relatedSubjects->firstWhere('subject_code', $registration->post_related_subject_code)?->subject_name;

    $gender = RegistrationReferencePresenter::codeTitle($registration->sex_code, $genderName, 'Unmapped gender code');
    $division = RegistrationReferencePresenter::codeTitle($registration->division_code, $divisionName, 'Unmapped division code');
    $district = RegistrationReferencePresenter::codeTitle($registration->district_code, $districtName, 'Unmapped district code');
    $university = RegistrationReferencePresenter::codeTitle($registration->university_code, $universityName, 'Unmapped university code');
    $bachelorSubject = RegistrationReferencePresenter::codeTitle($registration->bachelor_subject_code, $bachelorSubjectName, 'Unmapped bachelor subject code');
    $relatedSubject = RegistrationReferencePresenter::codeTitle($registration->post_related_subject_code, $relatedSubjectName, 'Unmapped post-related subject code');
    $category = $registration->cadre_category->code().' - '.$registration->cadre_category->label();
@endphp
<div class="page-header d-print-none"><div class="container-xl"><div class="row align-items-center"><div class="col"><div class="page-pretitle">{{ $registration->reg }} · {{ $registration->user_id }}</div><h2 class="page-title">{{ $registration->name }}</h2></div><div class="col-auto d-flex gap-2"><a class="btn btn-outline-secondary" href="{{ route('registrations.index') }}">Back</a>@can('update', $registration)<a class="btn btn-primary" href="{{ route('registrations.edit', $registration) }}">Edit</a>@endcan</div></div></div></div>
<div class="page-body"><div class="container-xl"><div class="row g-3"><div class="col-lg-8"><div class="card"><div class="card-header"><h3 class="card-title">Candidate information</h3></div><div class="card-body"><dl class="row mb-0">
@foreach(['National ID' => $registration->national_id, 'Name (Bangla)' => $registration->name_bn, "Father's name" => $registration->father_name, "Mother's name" => $registration->mother_name, 'Birth date' => $registration->birth_date?->format('d-m-Y'), 'Sex' => $gender, 'Division' => $division, 'District' => $district, 'University' => $university, 'Bachelor subject' => $bachelorSubject, 'Post-related subject (PRS)' => $relatedSubject] as $label => $value)<dt class="col-sm-4 py-2">{{ $label }}</dt><dd class="col-sm-8 py-2">{{ $value ?: '—' }}</dd>@endforeach
</dl></div></div></div><div class="col-lg-4"><div class="card mb-3"><div class="card-header"><h3 class="card-title">Processing facts</h3></div><div class="card-body"><dl class="row mb-0"><dt class="col-6">Category</dt><dd class="col-6">{{ $category }}</dd><dt class="col-6">Status</dt><dd class="col-6">{{ ucfirst($registration->status->value) }}</dd><dt class="col-6">Validation</dt><dd class="col-6">{{ ucfirst($registration->validation_status->value) }}</dd><dt class="col-6">Has quota</dt><dd class="col-6">{{ $registration->has_quota ? 'Yes' : 'No' }}</dd></dl></div></div><div class="card"><div class="card-header"><h3 class="card-title">Raw quota values</h3></div><div class="card-body"><dl class="row mb-0"><dt class="col-7">Freedom Fighter</dt><dd class="col-5">{{ $registration->has_ff_quota ?? 'NULL' }}</dd><dt class="col-7">Ethnic Minority</dt><dd class="col-5">{{ $registration->has_em_quota ?? 'NULL' }}</dd><dt class="col-7">PHC</dt><dd class="col-5">{{ $registration->has_phc_quota ?? 'NULL' }}</dd></dl>@if($registration->comment)<hr><div class="text-secondary">{{ $registration->comment }}</div>@endif</div></div></div></div>

<div class="card mt-3">
    <div class="card-header">
        <div>
            <h3 class="card-title">Manual correction audit history</h3>
            <div class="card-subtitle">Latest 50 audited edits for this registration.</div>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead><tr><th>Time (GMT+6)</th><th>Operator</th><th>Changed fields</th><th>Reason</th></tr></thead>
            <tbody>
            @forelse($audits as $audit)
                <tr>
                    <td class="text-nowrap">{{ $audit->created_at?->timezone('Asia/Dhaka')->format('d-m-Y h:i:s A') }}</td>
                    <td>{{ $audit->actor_name ?: ('User #'.$audit->actor_id) }}</td>
                    <td>
                        @foreach(($audit->changed_fields ?? []) as $field => $change)
                            <div class="mb-1"><strong>{{ $field }}</strong>: <code>{{ is_scalar($change['before'] ?? null) ? ($change['before'] ?? 'NULL') : json_encode($change['before'] ?? null) }}</code> → <code>{{ is_scalar($change['after'] ?? null) ? ($change['after'] ?? 'NULL') : json_encode($change['after'] ?? null) }}</code></div>
                        @endforeach
                    </td>
                    <td style="min-width: 240px">{{ $audit->reason }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="text-secondary text-center py-4">No manual correction has been recorded for this candidate.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
</div></div>
@endsection
