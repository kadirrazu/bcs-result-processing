<?php

namespace App\Queries\BachelorSubjects;

use App\Models\BachelorSubject;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/** Build the searchable bachelor subject master directory. */
final class ListBachelorSubjectsQuery
{
    public function execute(string $search, int $perPage = 25): LengthAwarePaginator
    {
        $perPage = in_array($perPage, [25, 50, 100], true) ? $perPage : 25;

        return BachelorSubject::query()->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->where('subject_code', 'like', "%{$search}%")->orWhere('subject_name', 'like', "%{$search}%")))->orderBy('subject_code')->paginate($perPage)->withQueryString();
    }
}
