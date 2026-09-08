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

    <div class="col-12"><hr class="my-1"><div class="text-secondary small">Optional examination metadata</div></div>

    <div class="col-md-4">
        <label for="bcs_type" class="form-label">BCS Type</label>
        <select id="bcs_type" name="bcs_type" class="form-select @error('bcs_type') is-invalid @enderror">
            <option value="">Not specified</option>
            @foreach ($types as $type)
                <option value="{{ $type->value }}" @selected(old('bcs_type', $examination->bcs_type?->value ?? '') === $type->value)>{{ $type->label() }}</option>
            @endforeach
        </select>
        @error('bcs_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label for="advertisement_date" class="form-label">Advertisement Date</label>
        <input id="advertisement_date" name="advertisement_date" type="date"
               value="{{ old('advertisement_date', isset($examination) && $examination->advertisement_date ? $examination->advertisement_date->format('Y-m-d') : '') }}"
               class="form-control @error('advertisement_date') is-invalid @enderror">
        <div class="form-hint">Official BCS circular / advertisement publication date.</div>
        @error('advertisement_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label for="age_calculation_date" class="form-label">Age Calculation Date</label>
        <input id="age_calculation_date" name="age_calculation_date" type="date"
               value="{{ old('age_calculation_date', isset($examination) && $examination->age_calculation_date ? $examination->age_calculation_date->format('Y-m-d') : '') }}"
               class="form-control @error('age_calculation_date') is-invalid @enderror">
        <div class="form-hint">Authoritative reference date for age-based Research &amp; Statistics reports.</div>
        @error('age_calculation_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <label class="form-check form-switch mt-2">
            <input type="hidden" name="is_completed" value="0">
            <input id="is_completed" class="form-check-input" type="checkbox" name="is_completed" value="1"
                   @checked((bool) old('is_completed', $examination->is_completed ?? false))>
            <span class="form-check-label fw-semibold">Mark as Completed</span>
        </label>
        <div class="form-hint">Administrative lock only. Completed examinations remain available for read-only lookup, reporting and export. Unlock this before any re-processing or processing-data mutation.</div>
        @error('is_completed')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const completed = document.getElementById('is_completed');
    if (!completed) return;

    let initial = completed.checked;
    completed.addEventListener('change', function () {
        if (completed.checked && !initial) {
            const ok = window.confirm('Mark this BCS as Completed? Processing mutations will be locked. Reporting and exports will remain available. You can unlock the examination later if re-processing is required.');
            if (!ok) completed.checked = false;
        }
    });
});
</script>
@endpush
