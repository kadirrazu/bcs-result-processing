<?php

namespace App\Http\Requests;

use App\Models\PostRelatedSubject;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdatePostRelatedSubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        $m = $this->route('post_related_subject');

        return $m instanceof PostRelatedSubject && ($this->user()?->can('update', $m) ?? false);
    }

    public function rules(): array
    {
        $m = $this->route('post_related_subject');

        return ['subject_code' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('post_related_subjects', 'subject_code')->ignore($m)], 'subject_name' => ['required', 'string', 'max:255'], 'is_active' => ['nullable', 'boolean']];
    }
}
