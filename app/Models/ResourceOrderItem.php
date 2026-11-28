<?php

namespace App\Models;

use App\Enums\PipelineStage;
use App\Traits\HasAuditTrail;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperResourceOrderItem
 */
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
        return $this->belongsTo(OrderItem::class, 'orderitem_id');
    }

    /**
     * Match bookings that overlap the given calendar day, not just ones whose `from` falls on
     * it. Without the `to` check, a booking starting right before midnight and ending shortly
     * after would be excluded from "today" even though it is still the earliest booking that day.
     */
    public function scopeOnDate(Builder $query, Carbon $date): Builder
    {
        return $query->where(function (Builder $query) use ($date) {
            $query->whereDate('from', $date->toDateString())
                ->orWhereDate('to', $date->toDateString());
        });
    }

    public function scopeOnOrderItemId(Builder $query, string $orderItemId): Builder
    {
        return $query->whereDate('orderitem_id', $orderItemId);
    }

    public function scopeForAfbDispatch(Builder $query): Builder
    {
        return $query
            ->whereHas('resource', fn ($q) => $q->whereNotNull('clinic_department_id'))
            ->whereHas('orderItem.order', fn ($q) => $q->whereIn('pipeline_stage_id', PipelineStage::getAfbDispatchAllowedStageIds()));
    }
}
