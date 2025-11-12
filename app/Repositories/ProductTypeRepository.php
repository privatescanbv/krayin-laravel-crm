<?php

namespace App\Repositories;

use App\Models\ProductType;
use Webkul\Core\Eloquent\Repository;

class ProductTypeRepository extends Repository
{
    /**
     * Searchable fields.
     */
    protected $fieldSearchable = [
        'name',
    ];

    public function model(): string
    {
        return ProductType::class;
    }
}
