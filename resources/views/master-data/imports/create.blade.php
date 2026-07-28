@extends('layouts.app')
@section('title', 'Import '.$definition->label())
@section('content')
<div class="page-header d-print-none">
    <div class="container-xl">
        <div class="row g-2 align-items-center">
            <div class="col"><div class="page-pretitle">Master Data</div><h2 class="page-title">Import {{ $definition->label() }}</h2></div>
            <div class="col-auto ms-auto d-flex gap-2">
                <a class="btn btn-outline-secondary" href="{{ route($definition->route()) }}">Back to List</a>
                <a class="btn btn-outline-primary" href="{{ route('master-data.imports.template', $definition->key) }}">Download Template</a>
            </div>
        </div>
    </div>
</div>
<div class="page-body"><div class="container-xl"><div class="card"><form method="POST" enctype="multipart/form-data" action="{{ route('master-data.imports.preview', $definition->key) }}">@csrf
    <div class="card-body">
        <div class="alert alert-info"><strong>Before uploading:</strong> use the downloaded template and do not rename or reorder columns. Supported formats: XLSX, XLS and CSV. Maximum size: 10 MB.</div>
        <div class="mb-3"><label class="form-label required">Excel or CSV file</label><input type="file" name="file" class="form-control @error('file') is-invalid @enderror" accept=".xlsx,.xls,.csv" required>@error('file')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div class="mb-3"><label class="form-label required">Duplicate mode</label><select class="form-select @error('mode') is-invalid @enderror" name="mode" required><option value="insert" @selected(old('mode', 'insert') === 'insert')>Insert new only (safest)</option><option value="update" @selected(old('mode') === 'update')>Update existing only</option><option value="upsert" @selected(old('mode') === 'upsert')>Insert new and update existing</option></select>@error('mode')<div class="invalid-feedback">{{ $message }}</div>@enderror<div class="form-hint">No database changes occur until you review the preview and confirm.</div></div>
    </div>
    <div class="card-footer text-end"><button class="btn btn-primary">Validate &amp; Preview</button></div>
</form></div></div></div>
@endsection
