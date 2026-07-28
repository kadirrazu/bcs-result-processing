<?php

namespace App\Http\Requests;

use App\Enums\CadreType;
use App\Models\CadreMaster;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateCadreMasterRequest extends FormRequest
{
    public function authorize(): bool
    {
        $m = $this->route('cadre_master');

        return $m instanceof CadreMaster && ($this->user()?->can('update', $m) ?? false);
    }

    public function rules(): array
    {
        $m = $this->route('cadre_master');

        return ['cadre_code' => ['required', 'integer', 'min:1', 'max:999999', Rule::unique('cadre_masters', 'cadre_code')->ignore($m)], 'cadre_abbr' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9]+$/', Rule::unique('cadre_masters', 'cadre_abbr')->ignore($m)], 'cadre_title' => ['required', 'string', 'max:255'], 'cadre_title_bn' => ['required', 'string', 'max:255'], 'cadre_type' => ['required', Rule::enum(CadreType::class)], 'display_order' => ['required', 'integer', 'min:0', 'max:65535'], 'is_active' => ['nullable', 'boolean']];
    }
}
