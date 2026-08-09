<?php

namespace App\Services\Circular;

use App\Models\CadreMaster;
use App\Models\CadreSubMaster;
use Illuminate\Validation\ValidationException;

final class CircularCodeResolver
{
    public function resolve(int $cadreCode, ?int $subCadreCode = null): array
    {
        $cadre = CadreMaster::query()->where('cadre_code', $cadreCode)->first();
        if (! $cadre) {
            throw ValidationException::withMessages(['cadre_code' => "Unknown cadre code {$cadreCode}."]);
        }
        if (! $cadre->is_active) {
            throw ValidationException::withMessages(['cadre_code' => "Cadre code {$cadreCode} is inactive in Cadre Master."]);
        }

        if ($subCadreCode === null) {
            return [
                'cadre' => $cadre,
                'sub' => null,
                'effective_code' => (int) $cadre->cadre_code,
                'cadre_type' => $cadre->cadre_type->value,
                'cadre_name' => $cadre->cadre_name,
                'cadre_name_bn' => $cadre->cadre_name_bn,
                'post_name' => $cadre->post_name,
                'post_name_bn' => $cadre->post_name_bn,
            ];
        }

        $sub = CadreSubMaster::query()
            ->with('parentCadre')
            ->where('sub_cadre_code', $subCadreCode)
            ->first();

        if (! $sub) {
            throw ValidationException::withMessages(['sub_cadre_code' => "Unknown sub-cadre code {$subCadreCode}."]);
        }
        if (! $sub->is_active) {
            throw ValidationException::withMessages(['sub_cadre_code' => "Sub-cadre code {$subCadreCode} is inactive."]);
        }
        if ((int) $sub->parentCadre?->cadre_code !== $cadreCode) {
            throw ValidationException::withMessages([
                'sub_cadre_code' => "Sub-cadre code {$subCadreCode} does not belong to parent cadre {$cadreCode}.",
            ]);
        }

        return [
            'cadre' => $cadre,
            'sub' => $sub,
            'effective_code' => (int) $sub->sub_cadre_code,
            'cadre_type' => $cadre->cadre_type->value,
            'cadre_name' => $cadre->cadre_name,
            'cadre_name_bn' => $cadre->cadre_name_bn,
            'post_name' => $sub->post_name,
            'post_name_bn' => $sub->post_name_bn,
        ];
    }
}
