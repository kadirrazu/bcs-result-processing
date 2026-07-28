<?php

namespace App\Actions\BachelorSubjects;

use App\Data\SubjectMasterData;
use App\Models\BachelorSubject;
use Illuminate\Support\Facades\DB;

/** Update a bachelor subject master record atomically. */
final class UpdateBachelorSubjectAction
{
    public function execute(BachelorSubject $subject, SubjectMasterData $data): BachelorSubject
    {
        return DB::transaction(function () use ($subject, $data) {
            $subject->update($data->toArray());

            return $subject->refresh();
        });
    }
}
