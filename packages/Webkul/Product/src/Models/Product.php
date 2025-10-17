<?php

namespace Webkul\Product\Models;

use App\Helpers\ProductHelper;
use App\Models\PartnerProduct;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Webkul\Activity\Models\ActivityProxy;
use Webkul\Activity\Traits\LogsActivity;
use Webkul\Attribute\Traits\CustomAttribute;
use Webkul\Product\Contracts\Product as ProductContract;
use Webkul\Product\Database\Factories\ProductFactory;
use Webkul\Tag\Models\TagProxy;
use App\Models\ResourceType;
use App\Models\ProductType;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Enums\Currency;

class Product extends Model implements ProductContract
{
    use CustomAttribute, HasFactory, LogsActivity;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return ProductFactory::new();
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'active',
        'currency',
        'description',
        'product_group_id',
        'price',
        'costs',
        'resource_type_id',
        'product_type_id',
    ];

    protected $casts = [
        'currency'         => 'string',
        'active'           => 'boolean',
        'price'            => 'decimal:2',
        'costs'            => 'decimal:2',
        'product_group_id' => 'integer',
        'product_type_id'  => 'integer',
        'resource_type_id' => 'integer',
    ];


    /**
     * The tags that belong to the Products.
     */
    public function tags()
    {
        return $this->belongsToMany(TagProxy::modelClass(), 'product_tags');
    }

    /**
     * Get the activities.
     */
    public function activities()
    {
        return $this->belongsToMany(ActivityProxy::modelClass(), 'product_activities');
    }

    /**
     * Get the product group that owns the product.
     */
    public function productGroup(): BelongsTo
    {
        return $this->belongsTo(ProductGroup::class);
    }

    public function resourceType(): BelongsTo
    {
        return $this->belongsTo(ResourceType::class);
    }

    public function productType(): BelongsTo
    {
        return $this->belongsTo(ProductType::class);
    }

    /**
     * Partner products linked to this product.
     */
    public function partnerProducts(): HasMany
    {
        return $this->hasMany(PartnerProduct::class, 'product_id');
    }

    /**
     * Normalize price on set.
     */
    public function setPriceAttribute($value): void
    {
        $this->attributes['price'] = Currency::normalizePrice($value);
    }

    /**
     * Normalize costs on set.
     */
    public function setCostsAttribute($value): void
    {
        $this->attributes['costs'] = Currency::normalizePrice($value);
    }

    public function getFullNameAttribute(): string {

        return ProductHelper::formatNameWithPath($this);
    }
}
