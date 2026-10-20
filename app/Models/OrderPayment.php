<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentType;
use App\Traits\HasAuditTrail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Patient Payments
 *
 * @mixin IdeHelperOrderPayment
 */
class OrderPayment extends Model
{
    use HasAuditTrail;

    protected $table = 'order_payments';

    protected $fillable = [
        'order_id',
        'amount',
        'type',
        'method',
        'paid_at',
        'currency',
        'auto_created',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'amount'       => 'decimal:2',
        'type'         => PaymentType::class,
        'method'       => PaymentMethod::class,
        'paid_at'      => 'date',
        'auto_created' => 'boolean',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
