@extends('layouts.app')
@section('title', 'Registration Details')
@section('content')
@php
    $gender = $genders->firstWhere('code', $registration->sex_code)?->name;
    $division = $divisions->firstWhere('code', $registration->division_code)?->name;
    $district = $districts->firstWhere('code', $registration->district_code)?->name;
    $universityName = $universities->firstWhere('code', $registration->university_code)?->name;
    $university = $registration->university_code === null
        ? null
        : ($universityName ?? $registration->university_code.' — Invalid University Code');
    $bachelorSubject = $bachelorSubjects->firstWhere('subject_code', $registration->bachelor_subject_code)?->subject_name;
    $relatedSubject = $relatedSubjects->firstWhere('subject_code', $registration->post_related_subject_code)?->subject_name;
@endphp
<div class="page-header d-print-none"><div class="container-xl"><div class="row align-items-center"><div class="col"><div class="page-pretitle">{{ $registration->reg }} · {{ $registration->user_id }}</div><h2 class="page-title">{{ $registration->name }}</h2></div><div class="col-auto d-flex gap-2"><a class="btn btn-outline-secondary" href="{{ route('registrations.index') }}">Back</a>@can('update', $registration)<a class="btn btn-primary" href="{{ route('registrations.edit', $registration) }}">Edit</a>@endcan</div></div></div></div>
<div class="page-body"><div class="container-xl"><div class="row g-3"><div class="col-lg-8"><div class="card"><div class="card-header"><h3 class="card-title">Candidate information</h3></div><div class="card-body"><dl class="row mb-0">
@foreach(['National ID' => $registration->national_id, 'Name (Bangla)' => $registration->name_bn, "Father's name" => $registration->father_name, "Mother's name" => $registration->mother_name, 'Birth date' => $registration->birth_date?->format('d-m-Y'), 'Sex' => $gender, 'Division' => $division, 'District' => $district, 'University' => $university, 'Bachelor subject' => $bachelorSubject, 'Post-related subject' => $relatedSubject] as $label => $value)<dt class="col-sm-4 py-2">{{ $label }}</dt><dd class="col-sm-8 py-2">{{ $value ?: '—' }}</dd>@endforeach
</dl></div></div></div><div class="col-lg-4"><div class="card mb-3"><div class="card-header"><h3 class="card-title">Processing facts</h3></div><div class="card-body"><dl class="row mb-0"><dt class="col-6">Category</dt><dd class="col-6">{{ $registration->cadre_category->code() }}</dd><dt class="col-6">Status</dt><dd class="col-6">{{ ucfirst($registration->status->value) }}</dd><dt class="col-6">Validation</dt><dd class="col-6">{{ ucfirst($registration->validation_status->value) }}</dd><dt class="col-6">Has quota</dt><dd class="col-6">{{ $registration->has_quota ? 'Yes' : 'No' }}</dd></dl></div></div><div class="card"><div class="card-header"><h3 class="card-title">Raw quota values</h3></div><div class="card-body"><dl class="row mb-0"><dt class="col-7">Freedom Fighter</dt><dd class="col-5">{{ $registration->has_ff_quota ?? 'NULL' }}</dd><dt class="col-7">Ethnic Minority</dt><dd class="col-5">{{ $registration->has_em_quota ?? 'NULL' }}</dd><dt class="col-7">PHC</dt><dd class="col-5">{{ $registration->has_phc_quota ?? 'NULL' }}</dd></dl>@if($registration->comment)<hr><div class="text-secondary">{{ $registration->comment }}</div>@endif</div></div></div></div></div></div>
@endsection
