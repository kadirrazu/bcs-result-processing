@php
    $editing = isset($examination);
@endphp

<div class="row g-3">
    <div class="col-md-3">
        <label for="bcs_number" class="form-label required">BCS Number</label>
        <input id="bcs_number" name="bcs_number" type="number" min="1" max="999"
               value="{{ old('bcs_number', $examination->bcs_number ?? '') }}"
               class="form-control @error('bcs_number') is-invalid @enderror" required>
        @error('bcs_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-9">
        <label for="name" class="form-label required">Name</label>
        <input id="name" name="name" type="text"
               value="{{ old('name', $examination->name ?? '') }}"
               class="form-control @error('name') is-invalid @enderror" required>
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label for="slug" class="form-label required">Slug</label>
        <input id="slug" name="slug" type="text"
               value="{{ old('slug', $examination->slug ?? '') }}"
               class="form-control @error('slug') is-invalid @enderror" placeholder="bcs-47" required>
        @error('slug')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label for="database_name" class="form-label required">Physical Database Name</label>
        <input id="database_name" name="database_name" type="text"
               value="{{ old('database_name', $examination->database_name ?? '') }}"
               class="form-control @error('database_name') is-invalid @enderror" placeholder="bcs_exam_47" required>
        <div class="form-hint">Registry only. This milestone does not create or drop the database.</div>
        @error('database_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label for="status" class="form-label required">Status</label>
        <select id="status" name="status" class="form-select @error('status') is-invalid @enderror" required>
            @foreach ($statuses as $status)
                <option value="{{ $status->value }}" @selected(old('status', $examination->status->value ?? 'draft') === $status->value)>
                    {{ $status->label() }}
                </option>
            @endforeach
        </select>
        @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6 d-flex align-items-end">
        <label class="form-check form-switch mb-2">
            <input type="hidden" name="is_enabled" value="0">
            <input class="form-check-input" type="checkbox" name="is_enabled" value="1"
                   @checked((bool) old('is_enabled', $examination->is_enabled ?? true))>
            <span class="form-check-label">Enabled for selection</span>
        </label>
    </div>
</div>
