@extends('layouts.app')
@section('title','Edit Circular Entry')
@section('content')
<div class="page-header"><div class="container-xl"><h2 class="page-title">Edit Circular Entry</h2><div class="text-secondary">Any actual change is audited. Editing an approved dataset automatically forks a new Draft version. Saving without a change does not fork or audit.</div></div></div>
<div class="page-body"><div class="container-xl">
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<div class="card mb-4"><form method="POST" action="{{ route('circular.entries.update',$entry) }}">@csrf @method('PUT')<div class="card-body">@include('circular._form')</div><div class="card-footer d-flex justify-content-between"><a class="btn btn-link" href="{{ route('circular.view') }}">Cancel</a><button class="btn btn-primary">Save audited correction</button></div></form></div>
<div class="card border-danger"><div class="card-header"><h3 class="card-title text-danger">Delete Circular Entry</h3></div><div class="card-body"><p class="text-secondary">Deletion is versioned and audited. If the current Circular is approved/confirmed/finalized, the system first preserves it and forks a new Draft version.</p><form method="POST" action="{{ route('circular.entries.destroy',$entry) }}" onsubmit="return confirm('Delete this Circular entry from the current working version? The previous version will remain preserved.');">@csrf @method('DELETE')<div class="mb-3"><label class="form-label required">Reason for deletion</label><textarea name="correction_reason" class="form-control" rows="3" required minlength="3"></textarea></div><button class="btn btn-danger">Delete entry with audit trail</button></form></div></div>
</div></div>
@endsection
