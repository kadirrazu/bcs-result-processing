@extends('layouts.app')
@section('title', 'Registrations')
@section('content')
@php
    $genderNames = $genders->pluck('name', 'code');
    $districtNames = $districts->pluck('name', 'code');
    $subjectNames = $bachelorSubjects->pluck('subject_name', 'subject_code');
@endphp
<div class="page-header d-print-none"><div class="container-xl"><div class="row g-2 align-items-center"><div class="col"><div class="page-pretitle">Active Examination</div><h2 class="page-title">Registrations</h2></div><div class="col-auto ms-auto d-flex gap-2"><a class="btn btn-outline-primary" href="{{ route('registrations.import') }}">Import Excel</a>@can('create', App\Models\Registration::class)<a class="btn btn-primary" href="{{ route('registrations.create') }}">Add Registration</a>@endcan</div></div></div></div>
<div class="page-body"><div class="container-xl">
<div class="card mb-3"><div class="card-body"><form method="get" class="row g-2">
<div class="col-lg-3"><input name="search" class="form-control" value="{{ $filters['search'] ?? '' }}" placeholder="Reg, User ID, NID or name"></div>
<div class="col-md-2"><select name="cadre_category" class="form-select"><option value="">All categories</option>@foreach($categories as $item)<option value="{{ $item->value }}" @selected((string)($filters['cadre_category'] ?? '') === (string)$item->value)>{{ $item->code() }}</option>@endforeach</select></div>
<div class="col-md-2"><select name="has_quota" class="form-select"><option value="">All quota states</option><option value="1" @selected(($filters['has_quota'] ?? '') === '1')>Has quota</option><option value="0" @selected(($filters['has_quota'] ?? '') === '0')>No quota</option></select></div>
<div class="col-md-2"><select name="status" class="form-select"><option value="">All statuses</option>@foreach($statuses as $item)<option value="{{ $item->value }}" @selected(($filters['status'] ?? '') === $item->value)>{{ ucfirst($item->value) }}</option>@endforeach</select></div>
<div class="col-md-3"><select name="district_code" class="form-select"><option value="">All districts</option>@foreach($districts as $item)<option value="{{ $item->code }}" @selected((string)($filters['district_code'] ?? '') === (string)$item->code)>{{ $item->name }}</option>@endforeach</select></div>
<div class="col-md-3"><select name="bachelor_subject_code" class="form-select"><option value="">All bachelor subjects</option>@foreach($bachelorSubjects as $item)<option value="{{ $item->subject_code }}" @selected((string)($filters['bachelor_subject_code'] ?? '') === (string)$item->subject_code)>{{ $item->subject_name }}</option>@endforeach</select></div>
<div class="col-auto"><button class="btn btn-outline-primary">Apply Filters</button></div><div class="col-auto"><a class="btn btn-outline-secondary" href="{{ route('registrations.index') }}">Clear</a></div>
</form></div></div>
<div class="card"><div class="table-responsive"><table class="table table-vcenter card-table"><thead><tr><th>SL</th><th>Registration</th><th>Candidate</th><th>Category</th><th>Sex</th><th>District</th><th>Subject</th><th>Quota</th><th>Status</th><th class="w-1"></th></tr></thead><tbody>
@forelse($records as $record)<tr><td>{{ ($records->firstItem() ?? 1) + $loop->index }}</td><td><a class="fw-semibold" href="{{ route('registrations.show', $record) }}">{{ $record->reg }}</a><div class="text-secondary small">{{ $record->user_id }}</div></td><td>{{ $record->name }}</td><td><span class="badge bg-azure-lt">{{ $record->cadre_category->code() }}</span></td><td>{{ $genderNames[$record->sex_code] ?? ($record->sex_code ?: '—') }}</td><td>{{ $districtNames[$record->district_code] ?? ($record->district_code ?: '—') }}</td><td>{{ $subjectNames[$record->bachelor_subject_code] ?? ($record->bachelor_subject_code ?: '—') }}</td><td><span class="badge {{ $record->has_quota ? 'bg-success-lt' : 'bg-secondary-lt' }}">{{ $record->has_quota ? 'Yes' : 'No' }}</span></td><td>{{ ucfirst($record->status->value) }}</td><td><a class="btn btn-sm btn-outline-primary" href="{{ route('registrations.show', $record) }}">View</a></td></tr>
@empty<tr><td colspan="10" class="text-center text-secondary py-5">No registrations matched the current filters.</td></tr>@endforelse
</tbody></table></div><div class="card-footer app-table-footer"><div class="app-table-summary">Showing {{ $records->firstItem() ?? 0 }}–{{ $records->lastItem() ?? 0 }} of {{ $records->total() }}</div>{{ $records->onEachSide(1)->links() }}</div></div>
</div></div>
@endsection
