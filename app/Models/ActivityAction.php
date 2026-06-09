<?php

namespace App\Models;

use App\Enums\ActivityActionType;
use App\Enums\CallStatus as CallStatusEnum;
use App\Traits\HasAuditTrail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Webkul\Activity\Models\Activity;

/**
 * @mixin IdeHelperActivityAction
 */
class ActivityAction extends Model
{
    use HasAuditTrail, HasFactory;

    protected $table = 'activity_actions';

    protected $fillable = [
        'activity_id',
        'type',
        'body',
        'call_status',
        'reschedule_days',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'type'        => ActivityActionType::class,
        'call_status' => CallStatusEnum::class,
    ];

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class, 'activity_id');
    }
}
