<?php

namespace App\Support\Pagination;

use Illuminate\Http\Request;

/** Resolve a safe, reusable page-size value for CRUD index pages. */
final readonly class PaginationSettings
{
    public const DEFAULT_PER_PAGE = 25;

    public const ALLOWED_PER_PAGE = [25, 50, 100];

    public function __construct(public int $perPage) {}

    public static function fromRequest(Request $request): self
    {
        $requested = $request->integer('per_page', self::DEFAULT_PER_PAGE);

        return new self(in_array($requested, self::ALLOWED_PER_PAGE, true)
            ? $requested
            : self::DEFAULT_PER_PAGE);
    }
}
