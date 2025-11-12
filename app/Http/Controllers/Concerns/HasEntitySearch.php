<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Prettus\Repository\Criteria\RequestCriteria;

trait HasEntitySearch
{
    /**
     * Perform entity search with query parameter conversion.
     *
     * This method converts the 'query' parameter to 'search' and 'searchFields' format
     * for RequestCriteria, allowing entity selectors to work with repositories.
     *
     * @param  mixed  $repository  The repository to search (defaults to $this->repository)
     * @return \Illuminate\Support\Collection
     */
    protected function performEntitySearch(Request $request, $repository = null)
    {
        $repository = $repository ?? $this->repository;

        // Convert 'query' parameter to 'search' and 'searchFields' for RequestCriteria
        // RequestCriteria reads from request()->query, so we need to modify that
        $query = $request->input('query');
        $originalQuery = request()->query->all();

        if ($query && ! $request->has('search')) {
            // Convert simple query to search format: name:query; with searchFields: name:like;
            $searchTerm = trim((string) $query);
            if (! empty($searchTerm)) {
                // Replace query parameters for RequestCriteria
                // RequestCriteria uses $request->get() which reads from query parameters
                request()->query->replace(array_merge($originalQuery, [
                    'search'       => 'name:'.$searchTerm.';',
                    'searchFields' => 'name:like;',
                    'searchJoin'   => 'or',
                ]));
            }
        }

        // Create RequestCriteria with the current request (which now has the modified query params)
        $criteria = new RequestCriteria(request());
        $entities = $repository
            ->pushCriteria($criteria)
            ->all();

        // Restore original query parameters
        request()->query->replace($originalQuery);

        return $entities;
    }
}
