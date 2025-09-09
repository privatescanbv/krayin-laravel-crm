<?php

namespace App\Models;

use App\Enums\CallStatus;
use App\Traits\HasAuditTrail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CallStatus extends Model
{
    use HasAuditTrail;

    protected $table = 'call_statuses';

    protected $fillable = [
        'activity_id',
        'status',
        'omschrijving',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'status' => CallStatus::class,
    ];

    public function activity(): BelongsTo
    {
        return $this->belongsTo(\Webkul\Activity\Models\Activity::class, 'activity_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\Webkul\User\Models\User::class, 'created_by');
    }
}

