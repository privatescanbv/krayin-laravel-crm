<?php

namespace App\Models;

use App\Traits\HasAuditTrail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasAuditTrail, HasFactory;

    protected $table = 'orders';

    protected $fillable = [
        'title',
        'total_price',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'total_price' => 'decimal:2',
        'status'      => \App\Enums\OrderStatus::class,
        'created_by'  => 'integer',
        'updated_by'  => 'integer',
    ];

    public function orderRegels(): HasMany
    {
        return $this->hasMany(OrderRegel::class);
    }
}
