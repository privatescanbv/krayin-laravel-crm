<?php

namespace App\Repositories;

use App\Models\ResourceType;
use Webkul\Core\Eloquent\Repository;

class ResourceTypeRepository extends Repository
{
    /**
     * Searchable fields.
     */
    protected $fieldSearchable = [
        'name',
    ];

    public function model(): string
    {
        return ResourceType::class;
    }
}
