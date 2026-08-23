<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StorePreviousBcsRepositoryDatasetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'bcs_number' => ['required', 'integer', 'min:1', 'max:999'],
            'file' => ['required', 'file', 'mimes:xlsx,csv', 'max:524288'],
        ];
    }
}
