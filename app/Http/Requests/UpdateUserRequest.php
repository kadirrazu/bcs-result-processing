<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $user = $this->route('user');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user),
            ],

            'designation_id' => [
                'required',
                'integer',
                Rule::exists('designations', 'id')
                    ->where('is_active', true),
            ],

            'role' => [
                'required',
                Rule::enum(UserRole::class),
            ],

            'password' => [
                'nullable',
                'confirmed',
                Password::min(8),
            ],

            'is_active' => [
                'nullable',
                'boolean',
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'designation_id' => 'designation',
        ];
    }
}