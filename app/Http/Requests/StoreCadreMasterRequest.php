<?php

namespace App\Http\Requests;

use App\Enums\CadreType;
use App\Models\CadreMaster;
use App\Models\CadreSubMaster;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreCadreMasterRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->can('create', CadreMaster::class) ?? false; }

    public function rules(): array
    {
        return [
            'cadre_code' => ['required','integer','min:1','max:999999','unique:cadre_masters,cadre_code', function ($attribute,$value,$fail): void {
                if (CadreSubMaster::query()->where('sub_cadre_code', (int) $value)->exists()) $fail('This code is already used by Sub Cadre Master.');
            }],
            'cadre_abbr' => ['required','string','max:20','regex:/^[A-Za-z0-9]+$/','unique:cadre_masters,cadre_abbr'],
            'cadre_name' => ['required','string','max:255'],
            'cadre_name_bn' => ['required','string','max:255'],
            'post_name' => ['nullable','string','max:255'],
            'post_name_bn' => ['nullable','string','max:255'],
            'cadre_type' => ['required', Rule::enum(CadreType::class)],
            'display_order' => ['required','integer','min:0','max:65535'],
            'is_active' => ['nullable','boolean'],
        ];
    }
}
