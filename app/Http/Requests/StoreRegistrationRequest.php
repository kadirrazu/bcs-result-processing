<?php

namespace App\Http\Requests;

use App\Enums\CadreCategory;
use App\Enums\RegistrationStatus;
use App\Models\Registration;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Validate manual registration input before it reaches the examination database. */
class StoreRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Registration::class) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'alpha_num', 'max:10', Rule::unique('exam.registrations', 'user_id')->ignore($this->registrationId())],
            'reg' => ['required', 'digits_between:1,8', Rule::unique('exam.registrations', 'reg')->ignore($this->registrationId())],
            'national_id' => ['nullable', 'string', 'max:25'],
            'name' => ['required', 'string', 'max:150'],
            'father_name' => ['nullable', 'string', 'max:150'],
            'mother_name' => ['nullable', 'string', 'max:150'],
            'name_bn' => ['nullable', 'string', 'max:200'],
            'father_name_bn' => ['nullable', 'string', 'max:200'],
            'mother_name_bn' => ['nullable', 'string', 'max:200'],
            'birth_date' => ['nullable', 'date'],
            'sex_code' => ['nullable', Rule::exists('genders', 'code')->where('is_active', true)],
            'district_code' => ['nullable', Rule::exists('districts', 'code')->where('is_active', true)],
            'division_code' => ['nullable', Rule::exists('divisions', 'code')->where('is_active', true)],
            'university_code' => ['nullable', Rule::exists('universities', 'code')->where('is_active', true)],
            'bachelor_subject_code' => ['nullable', Rule::exists('bachelor_subjects', 'subject_code')->where('is_active', true)],
            'related_subject_code' => ['nullable', Rule::exists('post_related_subjects', 'subject_code')->where('is_active', true)],
            'cadre_category' => ['required', Rule::enum(CadreCategory::class)],
            'has_ff_quota' => ['nullable', 'integer', 'min:0'],
            'has_em_quota' => ['nullable', 'integer', 'min:0'],
            'has_phc_quota' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', Rule::enum(RegistrationStatus::class)],
            'comment' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $nullable = ['national_id','father_name','mother_name','name_bn','father_name_bn','mother_name_bn','birth_date','sex_code','district_code','division_code','university_code','bachelor_subject_code','related_subject_code','has_ff_quota','has_em_quota','has_phc_quota','comment'];
        $values = [];
        foreach ($nullable as $field) {
            $value = $this->input($field);
            $values[$field] = is_string($value) && trim($value) === '' ? null : $value;
        }
        $values['user_id'] = strtoupper(trim((string) $this->input('user_id')));
        $values['reg'] = trim((string) $this->input('reg'));
        $this->merge($values);
    }

    private function registrationId(): ?int
    {
        $registration = $this->route('registration');
        return $registration instanceof Registration ? $registration->getKey() : null;
    }
}
