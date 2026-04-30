<?php

namespace App\Models;

use App\Enums\OrderItemStatus;
use App\Enums\PurchasePriceType;
use App\Enums\ResourceType as ResourceTypeEnum;
use App\Traits\HasAuditTrail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Facades\Log;
use Webkul\Contact\Models\Person;
use Webkul\Product\Models\Product;

/**
 * @mixin IdeHelperOrderItem
 */
class OrderItem extends Model
{
    use HasAuditTrail, HasFactory;

    protected $table = 'order_items';

    protected $appends = ['can_plan'];

    protected $fillable = [
        'order_id',
        'product_id',
        'resource_type_id',
        'name',
        'description',
        'afb_description',
        'person_id',
        'quantity',
        'total_price',
        'currency',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'order_id'         => 'integer',
        'product_id'       => 'integer',
        'resource_type_id' => 'integer',
        'person_id'        => 'integer',
        'quantity'         => 'integer',
        'total_price'      => 'decimal:2',
        'currency'         => 'string',
        'status'           => OrderItemStatus::class,
        'created_by'       => 'integer',
        'updated_by'       => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Optional override of the product's resource type for planning (same FK name as on products).
     */
    public function resourceType(): BelongsTo
    {
        return $this->belongsTo(ResourceType::class);
    }

    /**
     * Effective product type for planning/UI: derived from order-item resource type override when set,
     * otherwise the product's product type.
     */
    public function resolvedProductType(): ?ProductType
    {
        return $this->productType ?? $this->product?->productType;
    }

    /**
     * Effective resource type name for planning/monitor.
     *
     * Uses order-item resource type override when set; otherwise from product or first partner product.
     */
    public function resolvedResourceTypeName(): ?string
    {
        return $this->resolvedResourceType()?->label();
    }

    /**
     * Effective resource type name for planning/monitor.
     *
     * Uses order-item resource type override when set; otherwise from product or first partner product.
     */
    public function resolvedResourceType(): ResourceTypeEnum
    {
        if (! empty($this->resource_type_id)) {
            $name = $this->relationLoaded('resourceType')
                ? $this->resourceType?->name
                : $this->resourceType()->value('name');

            if ($name) {
                $resolved = ResourceTypeEnum::mapFrom($name);
                if ($resolved !== null) {
                    return $resolved;
                }
            }
        }
        $resolveResourceType = $this->product?->resolvedResourceTypeEnum();
        if ($resolveResourceType) {
            return $resolveResourceType;
        }
        // fallback
        Log::error('Could resolve resource type for order item'.$this->id.', fallback to default OTHER');

        return ResourceTypeEnum::OTHER;
    }

    public function isPlannable(): bool
    {
        return $this->resolvedResourceType()?->isPlannable() ?: false;
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function purchasePrice(): MorphOne
    {
        return $this->morphOne(PurchasePrice::class, 'priceable')
            ->where('type', PurchasePriceType::MAIN);
    }

    /**
     * Returns the effective purchase price for this order item.
     *
     * When a MAIN {@see PurchasePrice} exists on the line (e.g. from SugarCRM import or admin),
     * only that row is used — no per-field fallback to the catalog product or partner products.
     * Otherwise resolution order per field is: 1. product, 2. partner products (summed).
     */
    public function resolvedPurchasePrice(): object
    {
        $suffixes = PurchasePrice::priceSuffixes();
        $orderItemPrice = $this->relationLoaded('purchasePrice')
            ? $this->purchasePrice
            : $this->purchasePrice()->first();

        if ($orderItemPrice !== null) {
            $data = [];
            $total = 0.0;
            foreach ($suffixes as $suffix) {
                $field = 'purchase_price_'.$suffix;
                $value = (float) ($orderItemPrice->{$field} ?? 0);
                $data[$field] = (string) round($value, 2);
                $total += $value;
            }
            $data['purchase_price'] = (string) round($total, 2);

            return (object) $data;
        }

        $this->loadMissing(['product.partnerProducts.purchasePrice']);

        $product = $this->product;
        $productPrice = $product && method_exists($product, 'purchasePrice') ? $product->purchasePrice : null;

        $partnerProductTotals = null;
        if ($product && ! $product->partnerProducts->isEmpty()) {
            $partnerProductTotals = array_fill_keys($suffixes, 0.0);
            foreach ($product->partnerProducts as $pp) {
                if (! $pp->purchasePrice) {
                    continue;
                }
                foreach ($suffixes as $suffix) {
                    $partnerProductTotals[$suffix] += (float) ($pp->purchasePrice->{'purchase_price_'.$suffix} ?? 0);
                }
            }
        }

        $data = [];
        $total = 0.0;
        foreach ($suffixes as $suffix) {
            $field = 'purchase_price_'.$suffix;
            $productValue = $productPrice?->{$field} ?? null;
            $partnerProductValue = $partnerProductTotals[$suffix] ?? null;

            $value = $productValue ?? $partnerProductValue ?? 0;
            $value = (float) $value;
            $data[$field] = (string) round($value, 2);
            $total += $value;
        }
        $data['purchase_price'] = (string) round($total, 2);

        return (object) $data;
    }

    public function invoicePurchasePrice(): MorphOne
    {
        return $this->morphOne(PurchasePrice::class, 'priceable')
            ->where('type', PurchasePriceType::INVOICE);
    }

    public function resourceOrderItems(): HasMany
    {
        return $this->hasMany(ResourceOrderItem::class, 'orderitem_id');
    }

    public function scopeWithPartnerProductCount(Builder $query): Builder
    {
        return $query->select('order_items.id', 'order_items.status', 'order_items.product_id')
            ->selectRaw('COUNT(partner_products.id) AS partner_product_count')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->leftJoin('partner_products', 'products.id', '=', 'partner_products.product_id')
            ->groupBy('order_items.id', 'order_items.status', 'order_items.product_id');
    }

    public function scopeForOrderAndNotLostAndNew(Builder $query, string $orderId): Builder
    {
        return $query->where('order_id', $orderId)
            ->whereNotIn('status', [OrderItemStatus::LOST->value, OrderItemStatus::NEW->value]);
    }

    public function getProductName(): string
    {
        if (! empty($this->name)) {
            return $this->name;
        }

        return $this->product?->name ?? '';
    }

    public function getProductDescription(): string
    {
        if (! empty($this->description)) {
            return $this->description;
        }

        return $this->product?->ndescriptioname ?? '';
    }

    public function getCanPlanAttribute(): string
    {
        return $this->isPlannable();
    }
}
