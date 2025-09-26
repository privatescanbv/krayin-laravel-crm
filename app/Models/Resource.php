<?php

namespace App\Models;

use App\Traits\HasAuditTrail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Resource extends Model
{
    use HasAuditTrail, HasFactory;

    protected $table = 'resources';

    protected $fillable = [
        'resource_type_id',
        'name',
        'clinic_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'clinic_id'        => 'integer',
        'resource_type_id' => 'integer',
        'created_by'       => 'integer',
        'updated_by'       => 'integer',
    ];

    public function clinic()
    {
        return $this->belongsTo(Clinic::class);
    }

    public function resourceType()
    {
        return $this->belongsTo(ResourceType::class);
    }
}
