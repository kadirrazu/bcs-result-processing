<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
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
                'unique:users,email',
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
                'required',
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