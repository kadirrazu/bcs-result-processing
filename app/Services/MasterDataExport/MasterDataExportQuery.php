<?php

namespace App\Services\MasterDataExport;

use Illuminate\Database\Eloquent\Collection;

/** Load the complete master dataset in its official display order. */
final class MasterDataExportQuery
{
    public function execute(MasterDataExportDefinition $definition): Collection
    {
        $query = $definition->model()::query();

        return match ($definition->key()) {
            'cadre-masters' => $query->orderBy('display_order')->orderBy('cadre_code')->get(),
            'cadre-sub-masters' => $query->with('parentCadre')->orderBy('parent_cadre_id')->orderBy('display_order')->orderBy('sub_cadre_code')->get(),
            default => $query->orderBy('subject_code')->get(),
        };
    }
}
