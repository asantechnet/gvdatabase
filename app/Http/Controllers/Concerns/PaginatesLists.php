<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;

/**
 * Opt-in pagination for list endpoints.
 *
 * The response shape does not change unless the caller sends `per_page`: the
 * options/pickers and reports that fetch the whole list keep receiving the
 * plain array, while data tables request one page at a time.
 *
 * A paged response keeps `data` as the page's rows (the documented payload
 * path) and adds the paginator's counts beside it, so both shapes are read the
 * same way on the frontend.
 */
trait PaginatesLists
{
    /** Rows per page when the caller asks for one; null means "return all". */
    protected function requestedPerPage(Request $request): ?int
    {
        $perPage = $request->integer('per_page');

        return $perPage > 0 ? min($perPage, 100) : null;
    }

    /**
     * One page of `$query` as `{ data, current_page, last_page, per_page, total }`,
     * merged into the endpoint's own envelope.
     *
     * @param  callable(mixed[]): mixed[]|null  $map  Resource mapper for the page's rows.
     */
    protected function paginatedPayload(Request $request, mixed $query, ?callable $map = null): array
    {
        $paginator = $query->paginate($this->requestedPerPage($request))->withQueryString();
        $rows = $map ? $map($paginator->items()) : $paginator->items();

        return [
            'data' => array_values($rows),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];
    }
}
