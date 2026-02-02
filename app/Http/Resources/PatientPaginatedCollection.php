<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Base collection for patient index endpoints that return:
 * - data: array
 * - meta: { current_page, per_page, total }
 *
 * This intentionally does NOT return Laravel's default pagination links/meta format.
 */
abstract class PatientPaginatedCollection extends ResourceCollection
{
    /**
     * @var array{current_page:int, per_page:int, total:int}
     */
    protected array $meta;

    /**
     * @param  mixed  $resource
     * @param  array{current_page:int, per_page:int, total:int}  $meta
     */
    public function __construct($resource, array $meta)
    {
        parent::__construct($resource);
        $this->meta = $meta;
    }

    /**
     * Create a paginated collection response from a paginator, but using a separate
     * resource collection (e.g. after mapping paginator items to arrays).
     *
     * @param  mixed  $resource
     */
    public static function fromPaginator(LengthAwarePaginator $paginator, $resource): static
    {
        return new static($resource, [
            'current_page' => $paginator->currentPage(),
            'per_page'     => $paginator->perPage(),
            'total'        => $paginator->total(),
        ]);
    }

    public static function empty(int $perPage = 15): static
    {
        return new static(collect(), [
            'current_page' => 1,
            'per_page'     => $perPage,
            'total'        => 0,
        ]);
    }

    public function with(Request $request): array
    {
        return [
            'meta' => $this->meta,
        ];
    }
}
