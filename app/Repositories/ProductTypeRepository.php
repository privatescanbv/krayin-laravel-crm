<?php

namespace App\Repositories;

use App\Models\ProductType;
use Webkul\Core\Eloquent\Repository;

class ProductTypeRepository extends Repository
{
    public function model(): string
    {
        return ProductType::class;
    }
}

