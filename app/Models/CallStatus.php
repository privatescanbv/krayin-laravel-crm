<?php

namespace App\Models;

use App\Enums\CallStatus as CallStatusEnum;
use App\Traits\HasAuditTrail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Webkul\Activity\Models\Activity;

/**
 * @mixin IdeHelperCallStatus
 */
class CallStatus extends Model
{
    use HasAuditTrail, HasFactory;

    protected $table = 'call_statuses';

    protected $fillable = [
        'activity_id',
        'status',
        'omschrijving',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'status' => CallStatusEnum::class,
    ];

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class, 'activity_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
