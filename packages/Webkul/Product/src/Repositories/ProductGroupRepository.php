<?php

namespace Webkul\Product\Repositories;

use Webkul\Core\Eloquent\Repository;
use Webkul\Product\Models\ProductGroup;

class ProductGroupRepository extends Repository
{
    /**
     * Specify Model class name
     */
    public function model(): string
    {
        return ProductGroup::class;
    }
}
