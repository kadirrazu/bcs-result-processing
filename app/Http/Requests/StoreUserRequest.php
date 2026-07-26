<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Validate and authorize creation of a central application user.
 */
class StoreUserRequest extends FormRequest
{
    /**
     * Determine whether the authenticated user may create users.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', User::class) ?? false;
    }

    /**
     * Validation rules for user creation.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'designation_id' => [
                'required',
                'integer',
                Rule::exists('designations', 'id')->where('is_active', true),
            ],
            'role' => ['required', Rule::enum(UserRole::class)],
            'password' => ['required', 'confirmed', Password::min(8)],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * User-friendly validation attribute names.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'designation_id' => 'designation',
        ];
    }
}
