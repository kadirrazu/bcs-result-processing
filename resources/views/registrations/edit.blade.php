@extends('layouts.app')
@section('title', 'Edit Registration')
@section('content')
<div class="page-header d-print-none"><div class="container-xl"><div class="row align-items-center"><div class="col"><div class="page-pretitle">Registration {{ $registration->reg }}</div><h2 class="page-title">Edit Candidate</h2></div><div class="col-auto"><a href="{{ route('registrations.show', $registration) }}" class="btn btn-outline-secondary">Back</a></div></div></div></div>
<div class="page-body"><div class="container-xl"><form method="post" action="{{ route('registrations.update', $registration) }}">@csrf @method('put') @include('registrations._form')
<div class="card mb-3 border-warning">
    <div class="card-header"><h3 class="card-title">Audit reason</h3></div>
    <div class="card-body">
        <label class="form-label required" for="edit_reason">Reason for this correction</label>
        <textarea id="edit_reason" name="edit_reason" rows="3" class="form-control @error('edit_reason') is-invalid @enderror" required maxlength="2000" placeholder="State the verified reason / authority reference for changing this registration.">{{ old('edit_reason') }}</textarea>
        @error('edit_reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div class="form-hint">Every actual data change is stored with before/after values, operator, GMT+6 timestamp, IP and file log.</div>
    </div>
</div>
<div class="d-flex justify-content-end gap-2"><a href="{{ route('registrations.show', $registration) }}" class="btn btn-link">Cancel</a><button class="btn btn-primary">Update Registration</button></div></form></div></div>
@endsection
