<?php

namespace App\Repositories;

use App\Models\Resource;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Webkul\Core\Eloquent\Repository;

class ResourceRepository extends Repository
{
    public function model(): string
    {
        return Resource::class;
    }

    /**
     * Base query for resources that belong to active clinics.
     */
    public function queryWithActiveClinics(): Builder
    {
        return Resource::query()
            ->whereHas('clinic', function ($q) {
                $q->where('is_active', true);
            });
    }

    /**
     * Get all resources from active clinics, with optional eager loads and selected columns.
     */
    public function allWithActiveClinics(array $with = [], array $columns = ['*']): Collection|array
    {
        $query = $this->queryWithActiveClinics();

        if (! empty($with)) {
            $query->with($with);
        }

        return $query->get($columns);
    }

}
