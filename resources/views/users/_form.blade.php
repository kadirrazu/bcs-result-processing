@php
    $editing = isset($user);
    $selectedRole = old(
        'role',
        $editing ? $user->role->value : 'operator'
    );
@endphp

<div class="row g-3">
    <div class="col-md-6">
        <label for="name" class="form-label">
            Name <span class="text-danger">*</span>
        </label>

        <input
            type="text"
            id="name"
            name="name"
            value="{{ old('name', $user->name ?? '') }}"
            class="form-control @error('name') is-invalid @enderror"
            required
            autofocus
        >

        @error('name')
            <div class="invalid-feedback">
                {{ $message }}
            </div>
        @enderror
    </div>

    <div class="col-md-6">
        <label for="email" class="form-label">
            Email <span class="text-danger">*</span>
        </label>

        <input
            type="email"
            id="email"
            name="email"
            value="{{ old('email', $user->email ?? '') }}"
            class="form-control @error('email') is-invalid @enderror"
            required
        >

        @error('email')
            <div class="invalid-feedback">
                {{ $message }}
            </div>
        @enderror
    </div>

    <div class="col-md-6">
        <label for="designation_id" class="form-label">
            Designation <span class="text-danger">*</span>
        </label>

        <select
            id="designation_id"
            name="designation_id"
            class="form-select @error('designation_id') is-invalid @enderror"
            required
        >
            <option value="">Select designation</option>

            @foreach ($designations as $designation)
                <option
                    value="{{ $designation->id }}"
                    @selected(
                        old(
                            'designation_id',
                            $user->designation_id ?? ''
                        ) == $designation->id
                    )
                >
                    {{ $designation->name }}
                </option>
            @endforeach
        </select>

        @error('designation_id')
            <div class="invalid-feedback">
                {{ $message }}
            </div>
        @enderror
    </div>

    <div class="col-md-6">
        <label for="role" class="form-label">
            Role <span class="text-danger">*</span>
        </label>

        <select
            id="role"
            name="role"
            class="form-select @error('role') is-invalid @enderror"
            required
        >
            @foreach ($roles as $role)
                <option
                    value="{{ $role['value'] }}"
                    @selected($selectedRole === $role['value'])
                >
                    {{ $role['label'] }}
                </option>
            @endforeach
        </select>

        @error('role')
            <div class="invalid-feedback">
                {{ $message }}
            </div>
        @enderror
    </div>

    <div class="col-md-6">
        <label for="password" class="form-label">
            Password
            @unless ($editing)
                <span class="text-danger">*</span>
            @endunless
        </label>

        <input
            type="password"
            id="password"
            name="password"
            class="form-control @error('password') is-invalid @enderror"
            @required(! $editing)
        >

        @if ($editing)
            <small class="form-hint">
                Leave blank to keep the current password.
            </small>
        @endif

        @error('password')
            <div class="invalid-feedback">
                {{ $message }}
            </div>
        @enderror
    </div>

    <div class="col-md-6">
        <label for="password_confirmation" class="form-label">
            Confirm Password
        </label>

        <input
            type="password"
            id="password_confirmation"
            name="password_confirmation"
            class="form-control"
            @required(! $editing)
        >
    </div>

    <div class="col-12">
        <label class="form-check form-switch">
            <input
                type="hidden"
                name="is_active"
                value="0"
            >

            <input
                type="checkbox"
                name="is_active"
                value="1"
                class="form-check-input"
                @checked(
                    old(
                        'is_active',
                        isset($user) ? $user->is_active : true
                    )
                )
            >

            <span class="form-check-label">
                Active user
            </span>
        </label>

        @error('is_active')
            <div class="text-danger small mt-1">
                {{ $message }}
            </div>
        @enderror
    </div>
</div>