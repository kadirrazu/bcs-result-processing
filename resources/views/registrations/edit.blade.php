@extends('layouts.app')
@section('title', 'Edit Registration')
@section('content')
<div class="page-header d-print-none"><div class="container-xl"><div class="row align-items-center"><div class="col"><div class="page-pretitle">Registration {{ $registration->reg }}</div><h2 class="page-title">Edit Candidate</h2></div><div class="col-auto"><a href="{{ route('registrations.show', $registration) }}" class="btn btn-outline-secondary">Back</a></div></div></div></div>
<div class="page-body"><div class="container-xl"><form method="post" action="{{ route('registrations.update', $registration) }}">@csrf @method('put') @include('registrations._form')<div class="d-flex justify-content-end gap-2"><a href="{{ route('registrations.show', $registration) }}" class="btn btn-link">Cancel</a><button class="btn btn-primary">Update Registration</button></div></form></div></div>
@endsection
