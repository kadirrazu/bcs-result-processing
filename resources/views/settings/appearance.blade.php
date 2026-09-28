@extends('layouts.app')

@section('title', 'Appearance Settings')

@section('page-header')
<div class="row align-items-center">
    <div class="col">
        <div class="page-pretitle">My Settings</div>
        <h2 class="page-title">Appearance</h2>
    </div>
</div>
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-12 col-xl-10">
        <div class="card">
            <div class="card-header">
                <div>
                    <h3 class="card-title mb-1">UI Color Scheme</h3>
                    <div class="text-secondary small">Choose a light, low-saturation color scheme for your application workspace. Reports, print views, PDF/XLSX/DOCX exports and business-status colors are not changed.</div>
                </div>
            </div>
            <form method="POST" action="{{ route('settings.appearance.update') }}">
                @csrf
                @method('PUT')
                <div class="card-body">
                    <div class="row g-3">
                        @foreach ($schemes as $value => $scheme)
                            <div class="col-12 col-md-6 col-xl-4">
                                <label class="app-scheme-option {{ $selectedScheme === $value ? 'is-selected' : '' }}">
                                    <input class="form-check-input" type="radio" name="ui_color_scheme" value="{{ $value }}" {{ $selectedScheme === $value ? 'checked' : '' }}>
                                    <span class="app-scheme-option-body">
                                        <span class="d-flex align-items-center justify-content-between gap-2">
                                            <span class="fw-semibold">{{ $scheme['label'] }}</span>
                                            @if ($value === 'default')<span class="badge bg-secondary-lt">Original</span>@endif
                                        </span>
                                        <span class="app-scheme-swatches" aria-hidden="true">
                                            @foreach ($scheme['swatches'] as $swatch)<span style="background: {{ $swatch }}"></span>@endforeach
                                        </span>
                                        <span class="text-secondary small d-block">{{ $scheme['description'] }}</span>
                                    </span>
                                </label>
                            </div>
                        @endforeach
                    </div>
                    @error('ui_color_scheme')<div class="text-danger small mt-3">{{ $message }}</div>@enderror
                </div>
                <div class="card-footer d-flex flex-wrap justify-content-between gap-2">
                    <button type="submit" class="btn btn-primary">Apply Color Scheme</button>
                </div>
            </form>
        </div>

        <div class="card mt-3">
            <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div>
                    <div class="fw-semibold">Reset to Default</div>
                    <div class="text-secondary small">Remove your saved color preference and restore the original application UI.</div>
                </div>
                <form method="POST" action="{{ route('settings.appearance.reset') }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-secondary">Reset to Default</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
