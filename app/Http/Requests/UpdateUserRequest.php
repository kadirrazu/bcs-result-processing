<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Validate and authorize updates to a central application user.
 */
class UpdateUserRequest extends FormRequest
{
    /**
     * Determine whether the authenticated user may update the target user.
     */
    public function authorize(): bool
    {
        $user = $this->route('user');

        return $user instanceof User
            && ($this->user()?->can('update', $user) ?? false);
    }

    /**
     * Validation rules for user updates.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        /** @var User $user */
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user),
            ],
            'designation_id' => [
                'required',
                'integer',
                Rule::exists('designations', 'id')->where(
                    fn ($query) => $query
                        ->where('is_active', true)
                        ->orWhere('id', $user->designation_id)
                ),
            ],
            'role' => ['required', Rule::enum(UserRole::class)],
            'password' => ['nullable', 'confirmed', Password::min(8)],
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
