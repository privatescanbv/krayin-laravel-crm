<?php

namespace App\Models;

use App\Enums\OrderItemStatus;
use App\Traits\HasAuditTrail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Webkul\Product\Models\Product;

class OrderItem extends Model
{
    use HasAuditTrail, HasFactory;

    protected $table = 'order_items';

    protected $fillable = [
        'order_id',
        'product_id',
        'quantity',
        'total_price',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'order_id'    => 'integer',
        'product_id'  => 'integer',
        'quantity'    => 'integer',
        'total_price' => 'decimal:2',
        'status'      => OrderItemStatus::class,
        'created_by'  => 'integer',
        'updated_by'  => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function resourceOrderItems(): HasMany
    {
        return $this->hasMany(ResourceOrderItem::class, 'order_item_id');
    }
}