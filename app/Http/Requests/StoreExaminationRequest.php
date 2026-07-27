<?php

namespace App\Http\Requests;

use App\Enums\ExaminationStatus;
use App\Models\Examination;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validate creation of an examination registry entry.
 */
final class StoreExaminationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Examination::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'bcs_number' => ['required', 'integer', 'min:1', 'max:999', 'unique:examinations,bcs_number'],
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'unique:examinations,slug'],
            'database_name' => ['required', 'string', 'max:64', 'regex:/^[a-zA-Z][a-zA-Z0-9_]*$/', 'unique:examinations,database_name'],
            'status' => ['required', Rule::enum(ExaminationStatus::class)],
            'is_enabled' => ['nullable', 'boolean'],
        ];
    }
}
