<?php

namespace App\Models\Abstracts;

use App\Traits\HasAuditTrail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

abstract class BaseProduct extends Model
{
    use HasAuditTrail, HasFactory;

    protected $fillable = [
        'currency',
        'sales_price',
        'name',
        'active',
        'description',
        'discount_info',
        'resource_type_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'sales_price'      => 'decimal:2',
        'active'           => 'boolean',
        'resource_type_id' => 'integer',
        'created_by'       => 'integer',
        'updated_by'       => 'integer',
    ];
}

