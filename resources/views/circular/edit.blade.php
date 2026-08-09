@extends('layouts.app')
@section('title','Edit Circular Entry')
@section('content')
<div class="page-header"><div class="container-xl"><h2 class="page-title">Edit Circular Entry</h2><div class="text-secondary">Any change is audited. Editing an approved dataset automatically forks a new Draft version.</div></div></div>
<div class="page-body"><div class="container-xl">@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif<div class="card"><form method="POST" action="{{ route('circular.entries.update',$entry) }}">@csrf @method('PUT')<div class="card-body">@include('circular._form')</div><div class="card-footer d-flex justify-content-between"><a class="btn btn-link" href="{{ route('circular.view') }}">Cancel</a><button class="btn btn-primary">Save audited correction</button></div></form></div></div></div>
@endsection
