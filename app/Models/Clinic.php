<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasAuditTrail;

class Clinic extends Model
{
    use HasFactory, HasAuditTrail;

    protected $table = 'clinics';

    protected $fillable = [
        'name',
        'emails',
        'phones',
        'address_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'emails' => 'array',
        'phones' => 'array',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];

    public function address()
    {
        return $this->belongsTo(Address::class);
    }
}

