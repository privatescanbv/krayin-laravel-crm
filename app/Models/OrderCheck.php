<?php

namespace App\Models;

use App\Traits\HasAuditTrail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperOrderCheck
 */
class OrderCheck extends Model
{
    use HasAuditTrail, HasFactory;

    protected $table = 'order_checks';

    protected $fillable = [
        'order_id',
        'name',
        'done',
        'removable',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'order_id'   => 'integer',
        'done'       => 'boolean',
        'removable'  => 'boolean',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];

    public static function rules(): array
    {
        return [
            'order_id'  => 'required|integer|exists:orders,id',
            'name'      => 'required|string|max:255',
            'done'      => 'boolean',
            'removable' => 'boolean',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
