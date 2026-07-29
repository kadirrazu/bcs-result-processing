<?php

namespace App\Http\Requests;

use App\Models\Registration;

/** Apply the store rules while authorizing the concrete registration being edited. */
final class UpdateRegistrationRequest extends StoreRegistrationRequest
{
    public function authorize(): bool
    {
        $registration = $this->route('registration');

        return $registration instanceof Registration
            && ($this->user()?->can('update', $registration) ?? false);
    }
}
