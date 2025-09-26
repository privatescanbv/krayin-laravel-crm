<?php

namespace App\Models;

use App\Traits\HasAuditTrail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PartnerProduct extends Model
{
    use HasAuditTrail, HasFactory;

    protected $table = 'partner_products';

    protected $fillable = [
        'partner_name',
        'description',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];
}

