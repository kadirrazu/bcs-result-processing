<?php

namespace App\Http\Requests;

use App\Models\CadreMaster;
use App\Models\CadreSubMaster;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreCadreSubMasterRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->can('create', CadreSubMaster::class) ?? false; }

    public function rules(): array
    {
        return [
            'parent_cadre_id' => ['required','integer', Rule::exists('cadre_masters','id')->where('is_active', true)],
            'sub_cadre_code' => ['required','integer','min:1','max:999999','unique:cadre_sub_masters,sub_cadre_code', function ($attribute,$value,$fail): void {
                if (CadreMaster::query()->where('cadre_code', (int) $value)->exists()) $fail('This code is already used by Cadre Master.');
            }],
            'sub_cadre_abbr' => ['nullable','string','max:20','regex:/^[A-Za-z0-9]+$/','unique:cadre_sub_masters,sub_cadre_abbr'],
            'post_name' => ['required','string','max:255'],
            'post_name_bn' => ['required','string','max:255'],
            'display_order' => ['required','integer','min:0','max:65535'],
            'is_active' => ['nullable','boolean'],
        ];
    }
}
