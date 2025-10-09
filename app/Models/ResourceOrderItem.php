<?php

namespace App\Models;

use App\Traits\HasAuditTrail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResourceOrderItem extends Model
{
    use HasAuditTrail, HasFactory;

    protected $table = 'resource_order_items';

    protected $fillable = [
        'resource_id',
        'order_item_id',
        'from',
        'to',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'resource_id'   => 'integer',
        'order_item_id' => 'integer',
        'from'          => 'datetime',
        'to'            => 'datetime',
        'created_by'    => 'integer',
        'updated_by'    => 'integer',
    ];

    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }
}
