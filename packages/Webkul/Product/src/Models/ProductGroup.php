<?php

namespace Webkul\Product\Models;

use Illuminate\Database\Eloquent\Model;
use Webkul\Product\Contracts\ProductGroup as ProductGroupContract;

class ProductGroup extends Model implements ProductGroupContract
{
    protected $fillable = [
        'name',
        'description',
        'parent_id',
    ];

    /**
     * Get the products for the product group.
     */
    public function products()
    {
        return $this->hasMany(ProductProxy::modelClass());
    }

    /**
     * Get the parent group.
     */
    public function parent()
    {
        return $this->belongsTo(ProductGroupProxy::modelClass(), 'parent_id');
    }

    /**
     * Get the child groups.
     */
    public function children()
    {
        return $this->hasMany(ProductGroupProxy::modelClass(), 'parent_id');
    }

    /**
     * Get all descendants of the group.
     */
    public function descendants()
    {
        return $this->children()->with('descendants');
    }

    /**
     * Get all ancestors of the group.
     */
    public function ancestors()
    {
        return $this->parent()->with('ancestors');
    }
}
