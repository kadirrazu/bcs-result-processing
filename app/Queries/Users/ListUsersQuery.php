<?php

namespace App\Queries\Users;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Build the paginated, searchable central user directory.
 */
final class ListUsersQuery
{
    /**
     * @return LengthAwarePaginator<int, User>
     */
    public function execute(?string $search, int $perPage = 10): LengthAwarePaginator
    {
        $term = trim((string) $search);

        return User::query()
            ->with('designation')
            ->when($term !== '', function ($query) use ($term): void {
                $query->where(function ($query) use ($term): void {
                    $query
                        ->where('name', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%")
                        ->orWhereHas('designation', function ($query) use ($term): void {
                            $query->where('name', 'like', "%{$term}%");
                        });
                });
            })
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }
}
