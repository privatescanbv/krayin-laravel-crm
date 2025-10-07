<?php

namespace App\Models;

use App\Traits\HasAuditTrail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResourceOrderItem extends Model
{
    use HasAuditTrail, HasFactory;

    protected $table = 'resource_orderitem';

    protected $fillable = [
        'resource_id',
        'orderitem_id',
        'from',
        'to',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'resource_id'  => 'integer',
        'orderitem_id' => 'integer',
        'from'         => 'datetime',
        'to'           => 'datetime',
        'created_by'   => 'integer',
        'updated_by'   => 'integer',
    ];

    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderRegel::class, 'orderitem_id');
    }
}
