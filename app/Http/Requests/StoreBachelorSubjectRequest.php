<?php

namespace App\Http\Requests;

use App\Models\BachelorSubject;
use Illuminate\Foundation\Http\FormRequest;

final class StoreBachelorSubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', BachelorSubject::class) ?? false;
    }

    public function rules(): array
    {
        return ['subject_code' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9._-]+$/', 'unique:bachelor_subjects,subject_code'], 'subject_name' => ['required', 'string', 'max:255'], 'is_active' => ['nullable', 'boolean']];
    }
}
