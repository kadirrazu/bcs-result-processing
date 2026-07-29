@php
    use App\Enums\CadreCategory;
    use App\Enums\RegistrationStatus;
    $record = $registration ?? null;
@endphp

@if ($errors->any())
    <div class="alert alert-danger" role="alert">
        <div class="fw-bold mb-1">Please correct the highlighted fields.</div>
        <ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif

<div class="card mb-3">
    <div class="card-header"><h3 class="card-title">Candidate identity</h3></div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3"><label class="form-label required">User ID</label><input name="user_id" maxlength="10" required class="form-control @error('user_id') is-invalid @enderror" value="{{ old('user_id', $record?->user_id) }}">@error('user_id')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-md-3"><label class="form-label required">Registration No.</label><input name="reg" maxlength="8" required inputmode="numeric" class="form-control @error('reg') is-invalid @enderror" value="{{ old('reg', $record?->reg) }}">@error('reg')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-md-3"><label class="form-label">National ID</label><input name="national_id" maxlength="25" class="form-control" value="{{ old('national_id', $record?->national_id) }}"></div>
            <div class="col-md-3"><label class="form-label required">Cadre category</label><select name="cadre_category" class="form-select" required>@foreach(CadreCategory::cases() as $category)<option value="{{ $category->value }}" @selected((int) old('cadre_category', $record?->cadre_category?->value ?? 1) === $category->value)>{{ $category->code() }} — {{ $category->label() }}</option>@endforeach</select></div>
            <div class="col-md-4"><label class="form-label required">Name</label><input name="name" required maxlength="150" class="form-control" value="{{ old('name', $record?->name) }}"></div>
            <div class="col-md-4"><label class="form-label">Father's name</label><input name="father_name" maxlength="150" class="form-control" value="{{ old('father_name', $record?->father_name) }}"></div>
            <div class="col-md-4"><label class="form-label">Mother's name</label><input name="mother_name" maxlength="150" class="form-control" value="{{ old('mother_name', $record?->mother_name) }}"></div>
            <div class="col-md-4"><label class="form-label">Name (Bangla)</label><input name="name_bn" maxlength="200" class="form-control" value="{{ old('name_bn', $record?->name_bn) }}"></div>
            <div class="col-md-4"><label class="form-label">Father's name (Bangla)</label><input name="father_name_bn" maxlength="200" class="form-control" value="{{ old('father_name_bn', $record?->father_name_bn) }}"></div>
            <div class="col-md-4"><label class="form-label">Mother's name (Bangla)</label><input name="mother_name_bn" maxlength="200" class="form-control" value="{{ old('mother_name_bn', $record?->mother_name_bn) }}"></div>
            <div class="col-md-3"><label class="form-label">Birth date</label><input name="birth_date" type="date" class="form-control" value="{{ old('birth_date', $record?->birth_date?->format('Y-m-d')) }}"></div>
            <div class="col-md-3"><label class="form-label">Sex</label><select name="sex_code" class="form-select"><option value="">Select</option>@foreach($genders as $item)<option value="{{ $item->code }}" @selected((string) old('sex_code', $record?->sex_code) === (string) $item->code)>{{ $item->code }} — {{ $item->name }}</option>@endforeach</select></div>
            <div class="col-md-3"><label class="form-label required">Status</label><select name="status" class="form-select" required>@foreach(RegistrationStatus::cases() as $status)<option value="{{ $status->value }}" @selected(old('status', $record?->status?->value ?? 'active') === $status->value)>{{ ucfirst($status->value) }}</option>@endforeach</select></div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header"><h3 class="card-title">Central master references</h3></div>
    <div class="card-body"><div class="row g-3">
        <div class="col-md-3"><label class="form-label">Division</label><select name="division_code" class="form-select"><option value="">Select</option>@foreach($divisions as $item)<option value="{{ $item->code }}" @selected((string) old('division_code', $record?->division_code) === (string) $item->code)>{{ $item->name }}</option>@endforeach</select></div>
        <div class="col-md-3"><label class="form-label">District</label><select name="district_code" class="form-select"><option value="">Select</option>@foreach($districts as $item)<option value="{{ $item->code }}" data-division="{{ $item->division_code }}" @selected((string) old('district_code', $record?->district_code) === (string) $item->code)>{{ $item->name }}</option>@endforeach</select></div>
        <div class="col-md-6"><label class="form-label">University</label><select name="university_code" class="form-select"><option value="">Select</option>@foreach($universities as $item)<option value="{{ $item->code }}" @selected((string) old('university_code', $record?->university_code) === (string) $item->code)>{{ $item->code }} — {{ $item->name }}</option>@endforeach</select></div>
        <div class="col-md-6"><label class="form-label">Bachelor subject</label><select name="bachelor_subject_code" class="form-select"><option value="">Select</option>@foreach($bachelorSubjects as $item)<option value="{{ $item->subject_code }}" @selected((string) old('bachelor_subject_code', $record?->bachelor_subject_code) === (string) $item->subject_code)>{{ $item->subject_code }} — {{ $item->subject_name }}</option>@endforeach</select></div>
        <div class="col-md-6"><label class="form-label">Post-related subject</label><select name="related_subject_code" class="form-select"><option value="">Select</option>@foreach($relatedSubjects as $item)<option value="{{ $item->subject_code }}" @selected((string) old('related_subject_code', $record?->related_subject_code) === (string) $item->subject_code)>{{ $item->subject_code }} — {{ $item->subject_name }}</option>@endforeach</select></div>
    </div></div>
</div>

<div class="card mb-3">
    <div class="card-header"><h3 class="card-title">Raw quota values</h3></div>
    <div class="card-body"><div class="row g-3">
        @foreach(['has_ff_quota' => 'Freedom Fighter', 'has_em_quota' => 'Ethnic Minority', 'has_phc_quota' => 'Physically Challenged'] as $field => $label)
            <div class="col-md-4"><label class="form-label">{{ $label }}</label><input name="{{ $field }}" type="number" min="0" class="form-control" value="{{ old($field, $record?->$field) }}"><small class="form-hint">Blank remains NULL; imported numeric value is preserved.</small></div>
        @endforeach
        <div class="col-12"><label class="form-label">Comment</label><textarea name="comment" maxlength="2000" rows="3" class="form-control">{{ old('comment', $record?->comment) }}</textarea></div>
    </div></div>
</div>
