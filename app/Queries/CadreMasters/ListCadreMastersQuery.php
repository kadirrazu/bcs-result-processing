<?php

namespace App\Queries\CadreMasters;

use App\Models\CadreMaster;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/** Build the searchable central cadre master directory. */
final class ListCadreMastersQuery
{
    public function execute(string $search, int $perPage = 25): LengthAwarePaginator
    {
        $perPage = in_array($perPage, [25, 50, 100], true) ? $perPage : 25;

        return CadreMaster::query()->when($search !== '', fn ($q) => $q->where(function ($q) use ($search) {
            $q->where('cadre_code', 'like', "%{$search}%")->orWhere('cadre_abbr', 'like', "%{$search}%")->orWhere('cadre_title', 'like', "%{$search}%")->orWhere('cadre_title_bn', 'like', "%{$search}%");
        }))->orderBy('display_order')->orderBy('cadre_code')->paginate($perPage)->withQueryString();
    }
}
