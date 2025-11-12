<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Prettus\Repository\Contracts\CriteriaInterface;
use Prettus\Repository\Contracts\RepositoryInterface;
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

        $query = $request->input('query');
        $originalQuery = request()->query->all();

        // If 'query' parameter is provided, apply case-insensitive search on name field
        if ($query && ! $request->has('search')) {
            $searchTerm = trim((string) $query);
            if (! empty($searchTerm)) {
                $caseInsensitiveCriteria = new class($searchTerm) implements CriteriaInterface
                {
                    public function __construct(private string $searchTerm) {}

                    public function apply($model, RepositoryInterface $repository)
                    {
                        return $model->whereRaw('LOWER(name) LIKE ?', ['%'.strtolower($this->searchTerm).'%']);
                    }
                };
                $repository->pushCriteria($caseInsensitiveCriteria);
            }
        } else {
            // Use RequestCriteria for 'search' parameter or when no query is provided
            $criteria = new RequestCriteria(request());
            $repository->pushCriteria($criteria);
        }

        $entities = $repository->all();

        // Restore original query parameters
        request()->query->replace($originalQuery);

        return $entities;
    }
}
