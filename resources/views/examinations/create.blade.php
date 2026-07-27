@extends('layouts.app')
@section('title', 'Create Examination')
@section('content')
<div class="page-header d-print-none"><div class="container-xl"><h2 class="page-title">Create Examination</h2></div></div>
<div class="page-body"><div class="container-xl"><form method="POST" action="{{ route('examinations.store') }}">@csrf
<div class="card"><div class="card-body">@include('examinations._form')</div><div class="card-footer text-end"><a href="{{ route('examinations.index') }}" class="btn btn-link">Cancel</a><button class="btn btn-primary">Create Examination</button></div></div>
</form></div></div>
@endsection
