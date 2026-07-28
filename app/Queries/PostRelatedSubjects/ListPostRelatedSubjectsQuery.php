<?php

namespace App\Queries\PostRelatedSubjects;

use App\Models\PostRelatedSubject;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/** Build the searchable post-related subject master directory. */
final class ListPostRelatedSubjectsQuery
{
    public function execute(string $search, int $perPage = 25): LengthAwarePaginator
    {
        return PostRelatedSubject::query()->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->where('subject_code', 'like', "%{$search}%")->orWhere('subject_name', 'like', "%{$search}%")))->orderBy('subject_code')->paginate($perPage)->withQueryString();
    }
}
