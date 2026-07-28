<?php

namespace App\Actions\PostRelatedSubjects;

use App\Data\SubjectMasterData;
use App\Models\PostRelatedSubject;
use Illuminate\Support\Facades\DB;

/** Create a post-related subject master record atomically. */
final class CreatePostRelatedSubjectAction
{
    public function execute(SubjectMasterData $data): PostRelatedSubject
    {
        return DB::transaction(fn () => PostRelatedSubject::query()->create($data->toArray()));
    }
}
