<?php

namespace App\Actions\Examinations;

use App\Data\ExaminationData;
use App\Models\Examination;
use Illuminate\Support\Facades\DB;

/**
 * Create one central examination registry entry atomically.
 */
final class CreateExaminationAction
{
    public function execute(ExaminationData $data): Examination
    {
        return DB::transaction(
            fn (): Examination => Examination::query()->create($data->toArray())
        );
    }
}
