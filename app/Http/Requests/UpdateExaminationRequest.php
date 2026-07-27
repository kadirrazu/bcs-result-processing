<?php

namespace App\Http\Requests;

use App\Enums\ExaminationStatus;
use App\Models\Examination;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validate updates to an examination registry entry.
 */
final class UpdateExaminationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $examination = $this->route('examination');

        return $examination instanceof Examination
            && ($this->user()?->can('update', $examination) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var Examination $examination */
        $examination = $this->route('examination');

        return [
            'bcs_number' => ['required', 'integer', 'min:1', 'max:999', Rule::unique('examinations', 'bcs_number')->ignore($examination)],
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('examinations', 'slug')->ignore($examination)],
            'database_name' => ['required', 'string', 'max:64', 'regex:/^[a-zA-Z][a-zA-Z0-9_]*$/', Rule::unique('examinations', 'database_name')->ignore($examination)],
            'status' => ['required', Rule::enum(ExaminationStatus::class)],
            'is_enabled' => ['nullable', 'boolean'],
        ];
    }
}
