<?php

namespace App\Models;

use App\Actions\Leads\RecordLeadContactAction;
use App\Casts\NullableCallStatusCast;
use App\Enums\ActivityActionType;
use App\Traits\HasAuditTrail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Throwable;
use Webkul\Activity\Models\Activity;

/**
 * @mixin IdeHelperActivityAction
 */
class ActivityAction extends Model
{
    use HasAuditTrail, HasFactory;

    protected $table = 'activity_actions';

    protected $touches = ['activity'];

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
        'call_status' => NullableCallStatusCast::class,
    ];

    protected static function booted(): void
    {
        // A call logged after the lead was lost must still flip its had_contact.
        $recordContact = function (self $action): void {
            if ($action->type !== ActivityActionType::Belstatus) {
                return;
            }

            try {
                $activity = $action->activity()->select(['id', 'lead_id', 'sales_lead_id'])->first();

                if ($activity) {
                    app(RecordLeadContactAction::class)->executeFor($activity->lead_id, $activity->sales_lead_id);
                }
            } catch (Throwable $e) {
                report($e);
            }
        };

        static::saved($recordContact);
        static::deleted($recordContact);
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class, 'activity_id');
    }
}
