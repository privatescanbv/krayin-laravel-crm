<?php

namespace App\Models;

use App\Traits\HasAuditTrail;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Shift extends BaseModel
{
    use HasFactory, HasAuditTrail;

    protected $table = 'shifts';

    protected $fillable = [
        'resource_id',
        'starts_at',
        'ends_at',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'resource_id' => 'integer',
        'starts_at'   => 'datetime',
        'ends_at'     => 'datetime',
        'created_by'  => 'integer',
        'updated_by'  => 'integer',
    ];

    public function resource()
    {
        return $this->belongsTo(Resource::class);
    }
}

