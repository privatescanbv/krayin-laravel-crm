<?php

namespace App\Models;

use App\Support\Period;
use App\Traits\HasAuditTrail;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @mixin IdeHelperShift
 */
class Shift extends BaseModel
{
    use HasAuditTrail, HasFactory;

    protected $table = 'shifts';

    protected $fillable = [
        'resource_id',
        'notes',
        'available',
        // new period-based fields
        'period_start',
        'period_end',
        'weekday_time_blocks',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'resource_id'         => 'integer',
        'available'           => 'boolean',
        'period_start'        => 'date',
        'period_end'          => 'date',
        'weekday_time_blocks' => 'array',
        'created_by'          => 'integer',
        'updated_by'          => 'integer',
    ];

    public function resource()
    {
        return $this->belongsTo(Resource::class);
    }

    /**
     * Get the period for this shift.
     */
    public function period(): Period
    {
        $start = $this->period_start ? CarbonImmutable::parse($this->period_start) : null;
        $end = $this->period_end ? CarbonImmutable::parse($this->period_end) : null;

        return new Period($start, $end);
    }
}
