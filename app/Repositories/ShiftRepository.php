<?php

namespace App\Repositories;

use App\Models\Shift;
use Webkul\Core\Eloquent\Repository;

class ShiftRepository extends Repository
{
    public function model(): string
    {
        return Shift::class;
    }

    public function forResource(int $resourceId)
    {
        return $this->model->newQuery()->where('resource_id', $resourceId);
    }
}
