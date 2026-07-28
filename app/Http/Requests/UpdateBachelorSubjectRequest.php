<?php

namespace App\Http\Requests;

use App\Models\BachelorSubject;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateBachelorSubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        $m = $this->route('bachelor_subject');

        return $m instanceof BachelorSubject && ($this->user()?->can('update', $m) ?? false);
    }

    public function rules(): array
    {
        $m = $this->route('bachelor_subject');

        return ['subject_code' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('bachelor_subjects', 'subject_code')->ignore($m)], 'subject_name' => ['required', 'string', 'max:255'], 'is_active' => ['nullable', 'boolean']];
    }
}
