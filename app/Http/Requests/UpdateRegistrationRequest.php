<?php

namespace App\Http\Requests;

use App\Models\Registration;

/** Validate registration corrections and require an operator-supplied audit reason. */
final class UpdateRegistrationRequest extends StoreRegistrationRequest
{
    public function authorize(): bool
    {
        $registration = $this->route('registration');

        return $registration instanceof Registration
            && ($this->user()?->can('update', $registration) ?? false);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'edit_reason' => ['required', 'string', 'min:5', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $this->merge([
            'edit_reason' => trim((string) $this->input('edit_reason')),
        ]);
    }
}
