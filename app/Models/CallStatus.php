<?php

namespace App\Models;

use App\Actions\Leads\RecordLeadContactAction;
use App\Casts\NullableCallStatusCast;
use App\Traits\HasAuditTrail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Throwable;
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
        'status' => NullableCallStatusCast::class,
    ];

    protected static function booted(): void
    {
        // A call logged after the lead was lost must still flip its had_contact.
        $recordContact = function (self $callStatus): void {
            $activity = $callStatus->activity()->select(['id', 'lead_id', 'sales_lead_id'])->first();

            try {
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
