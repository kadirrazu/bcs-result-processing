<?php

namespace App\Actions\BachelorSubjects;

use App\Data\SubjectMasterData;
use App\Models\BachelorSubject;
use Illuminate\Support\Facades\DB;

/** Create a bachelor subject master record atomically. */
final class CreateBachelorSubjectAction
{
    public function execute(SubjectMasterData $data): BachelorSubject
    {
        return DB::transaction(fn () => BachelorSubject::query()->create($data->toArray()));
    }
}
