<?php

namespace Webkul\Product\Models;

use Illuminate\Database\Eloquent\Model;
use Webkul\Product\Contracts\ProductGroup as ProductGroupContract;

class ProductGroup extends Model implements ProductGroupContract
{
    protected $fillable = [
        'name',
        'description',
    ];

    /**
     * Get the products for the product group.
     */
    public function products()
    {
        return $this->hasMany(ProductProxy::modelClass());
    }
}
