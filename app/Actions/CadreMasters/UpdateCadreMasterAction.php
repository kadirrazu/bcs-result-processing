<?php

namespace App\Actions\CadreMasters;

use App\Data\CadreMasterData;
use App\Models\CadreMaster;
use Illuminate\Support\Facades\DB;

/** Update a central cadre master record atomically. */
final class UpdateCadreMasterAction
{
    public function execute(CadreMaster $cadreMaster, CadreMasterData $data): CadreMaster
    {
        return DB::transaction(function () use ($cadreMaster, $data) {
            $cadreMaster->update($data->toArray());

            return $cadreMaster->refresh();
        });
    }
}
