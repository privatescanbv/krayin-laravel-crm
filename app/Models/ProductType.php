<?php

namespace App\Models;

use App\Traits\HasAuditTrail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperProductType
 */
class ProductType extends Model
{
    use HasAuditTrail, HasFactory;

    protected $table = 'product_types';

    protected $fillable = [
        'external_id',
        'name',
        'description',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];
}
