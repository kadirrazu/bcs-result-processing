@extends('layouts.app')
@section('title','Add Circular Entry')
@section('content')
<div class="page-header"><div class="container-xl"><h2 class="page-title">Add Circular Entry</h2><div class="text-secondary">Choose a Master code; cadre/post identity is filled automatically. Every manual Circular change requires an audit reason.</div></div></div>
<div class="page-body"><div class="container-xl">@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif<div class="card"><form method="POST" action="{{ route('circular.entries.store') }}">@csrf<div class="card-body">@include('circular._form')</div><div class="card-footer d-flex justify-content-between"><a class="btn btn-link" href="{{ route('circular.index') }}">Cancel</a><button class="btn btn-primary">Create audited entry</button></div></form></div></div></div>
@endsection
