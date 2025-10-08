<?php

namespace App\Models;

use App\Traits\HasAuditTrail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderRegel extends Model
{
    use HasAuditTrail, HasFactory;

    protected $table = 'order_regels';

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
        'status'      => \App\Enums\OrderItemStatus::class,
        'created_by'  => 'integer',
        'updated_by'  => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(\Webkul\Product\Models\Product::class);
    }
}
