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
        'type',
        'name',
        'clinic_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'clinic_id'  => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];

    public function clinic()
    {
        return $this->belongsTo(Clinic::class);
    }
}
