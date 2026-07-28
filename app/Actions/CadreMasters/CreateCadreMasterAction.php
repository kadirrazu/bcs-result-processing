<?php

namespace App\Actions\CadreMasters;

use App\Data\CadreMasterData;
use App\Models\CadreMaster;
use Illuminate\Support\Facades\DB;

/** Create a central cadre master record atomically. */
final class CreateCadreMasterAction
{
    public function execute(CadreMasterData $data): CadreMaster
    {
        return DB::transaction(fn () => CadreMaster::query()->create($data->toArray()));
    }
}
