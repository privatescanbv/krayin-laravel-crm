<?php

namespace App\Models;

use App\Enums\AfbDispatchStatus;
use App\Enums\AfbDispatchType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Webkul\Email\Models\Email;

class AfbDispatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'clinic_id',
        'email_id',
        'type',
        'status',
        'order_ids',
        'sent_at',
        'last_attempt_at',
        'attempt',
        'error_message',
    ];

    protected $casts = [
        'clinic_id'        => 'integer',
        'email_id'         => 'integer',
        'type'             => AfbDispatchType::class,
        'status'           => AfbDispatchStatus::class,
        'order_ids'        => 'array',
        'sent_at'          => 'datetime',
        'last_attempt_at'  => 'datetime',
        'attempt'          => 'integer',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function email(): BelongsTo
    {
        return $this->belongsTo(Email::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(AfbDispatchOrder::class);
    }
}
