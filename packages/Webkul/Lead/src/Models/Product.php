<?php

namespace Webkul\Lead\Models;

use Illuminate\Database\Eloquent\Model;
use Webkul\Lead\Contracts\Product as ProductContract;
use Webkul\Product\Models\ProductProxy;

class Product extends Model implements ProductContract
{
    protected $table = 'lead_products';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'currency',
        'description',
        'product_group_id',
        'price',
        'costs',           // ✓ TOEGEVOEGD
        'resource_type_id',
        'product_type_id',
    ];

    protected $casts = [
        'currency'         => 'string',
        'price'            => 'decimal:2',
        'costs'            => 'decimal:2'
    ];

    /**
     * Get the product owns the lead product.
     */
    public function product()
    {
        return $this->belongsTo(ProductProxy::modelClass());
    }

    /**
     * Get the lead that owns the lead product.
     */
    public function lead()
    {
        return $this->belongsTo(LeadProxy::modelClass());
    }

    /**
     * Get the customer full name.
     */
    public function getNameAttribute()
    {
        return $this->product->name;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        $array = parent::toArray();

        $array['name'] = $this->name;

        return $array;
    }
}
