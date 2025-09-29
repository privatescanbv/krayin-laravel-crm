<?php

namespace App\Models;

use App\Traits\HasAuditTrail;
use Illuminate\Database\Eloquent\Factories\HasFactory;

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
}
