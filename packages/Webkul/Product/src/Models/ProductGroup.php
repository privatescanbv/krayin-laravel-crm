<?php

namespace Webkul\Product\Models;

use App\Traits\HasAuditTrail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Webkul\Product\Contracts\ProductGroup as ProductGroupContract;
use Webkul\Product\Repositories\ProductGroupRepository;

class ProductGroup extends Model implements ProductGroupContract
{
    use HasAuditTrail;
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'parent_id',
        'created_by',
        'updated_by',
    ];

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \Webkul\Product\Database\Factories\ProductGroupFactory::new();
    }

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
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * Get the child groups.
     */
    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
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

    /**
     * Get the full hierarchical path of the group.
     * Returns the name for root level (no parent).
     */
    public function getPathAttribute(): string
    {
        return app(ProductGroupRepository::class)->buildGroupPath($this);
    }
}
