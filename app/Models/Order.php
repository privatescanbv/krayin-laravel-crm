<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Helpers\ValueNormalizer;
use App\Traits\HasAuditTrail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Webkul\Activity\Models\Activity;

/**
 * @mixin IdeHelperOrder
 */
class Order extends Model
{
    use HasAuditTrail, HasFactory;

    protected $table = 'orders';

    protected $fillable = [
        'title',
        'total_price',
        'status',
        'first_examination_at',
        'sales_lead_id',
        'combine_order',
        'confirmation_letter_content',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'total_price'          => 'decimal:2',
        'status'               => OrderStatus::class,
        'first_examination_at' => 'datetime',
        'sales_lead_id'        => 'integer',
        'combine_order'        => 'boolean',
        'created_by'           => 'integer',
        'updated_by'           => 'integer',
    ];

    public static function rules(): array
    {
        return [
            'title'                => 'required|string|max:255',
            'total_price'          => 'required|numeric|min:0',
            'status'               => 'required|string',
            'first_examination_at' => 'nullable|date',
            'sales_lead_id'        => 'required|integer|exists:salesleads,id',
            'combine_order'        => 'boolean',
        ];
    }

    /**
     * Normalize empty datetime input to NULL (see ValueNormalizer::nullableDateTime()).
     */
    public function setFirstExaminationAtAttribute(mixed $value): void
    {
        $this->attributes['first_examination_at'] = ValueNormalizer::nullableDateTime($value);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function salesLead(): BelongsTo
    {
        return $this->belongsTo(SalesLead::class);
    }

    public function lead()
    {
        return $this->salesLead?->lead;
    }

    public function orderChecks(): HasMany
    {
        return $this->hasMany(OrderCheck::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }
}
