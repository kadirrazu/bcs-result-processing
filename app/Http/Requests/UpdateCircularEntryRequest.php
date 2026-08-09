<?php

namespace App\Http\Requests;

final class UpdateCircularEntryRequest extends StoreCircularEntryRequest
{
    public function rules(): array
    {
        return parent::rules() + ['correction_reason' => ['required', 'string', 'min:3', 'max:2000']];
    }
}
