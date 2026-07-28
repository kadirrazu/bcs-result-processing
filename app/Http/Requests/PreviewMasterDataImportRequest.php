<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Validate a master-data spreadsheet before preview generation. */
final class PreviewMasterDataImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:10240', 'mimes:xlsx,xls,csv'],
            'mode' => ['required', Rule::in(['insert', 'update', 'upsert'])],
        ];
    }
}
