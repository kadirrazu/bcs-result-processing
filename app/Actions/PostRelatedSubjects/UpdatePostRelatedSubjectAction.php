<?php

namespace App\Actions\PostRelatedSubjects;

use App\Data\SubjectMasterData;
use App\Models\PostRelatedSubject;
use Illuminate\Support\Facades\DB;

/** Update a post-related subject master record atomically. */
final class UpdatePostRelatedSubjectAction
{
    public function execute(PostRelatedSubject $subject, SubjectMasterData $data): PostRelatedSubject
    {
        return DB::transaction(function () use ($subject, $data) {
            $subject->update($data->toArray());

            return $subject->refresh();
        });
    }
}
