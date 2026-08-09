<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCircularEntryRequest extends FormRequest
{
    public function authorize(): bool { return $this->user() !== null; }
    public function rules(): array
    {
        return [
            'cadre_serial' => ['required', 'integer', 'min:1', 'max:65535'],
            'sub_serial' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'cadre_code' => ['required', 'integer', 'min:1'],
            'sub_cadre_code' => ['nullable', 'integer', 'min:1'],
            'post_count' => ['required', 'integer', 'min:1'],
            'status' => ['required', 'in:ACTIVE,INACTIVE,active,inactive'],
            'bachelor_subject_codes' => ['nullable', 'array'],
            'bachelor_subject_codes.*' => ['string', 'max:20'],
            'prs_codes' => ['nullable', 'array'],
            'prs_codes.*' => ['string', 'max:20'],
            'note' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
