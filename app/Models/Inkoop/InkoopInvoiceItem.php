<?php

namespace App\Models\Inkoop;

use App\Models\Clinic;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Webkul\Product\Models\Product;

class InkoopInvoiceItem extends Model
{
    use HasFactory;

    protected $table = 'inkoop_invoice_items';

    protected $fillable = [
        'clinic_id',
        'inkoop_invoice_id',
        'invoice_id',
        'person_id',
        'description',
        'quantity',
        'unit_price',
        'total_price',
        'name',
        'date',
        'price',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'date'        => 'date',
        'quantity'    => 'decimal:2',
        'unit_price'  => 'decimal:2',
        'total_price' => 'decimal:2',
        'price'       => 'float',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            $model->created_by = $model->created_by ?: auth()->guard('user')->id();
            $model->updated_by = $model->updated_by ?: auth()->guard('user')->id();
        });

        static::updating(function (self $model) {
            $model->updated_by = auth()->guard('user')->id();
        });
    }

    public function setInvoiceIdAttribute($value): void
    {
        $this->attributes['inkoop_invoice_id'] = $value;
    }

    public function getInvoiceIdAttribute()
    {
        return $this->attributes['inkoop_invoice_id'] ?? null;
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(InkoopInvoice::class, 'inkoop_invoice_id');
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(InkoopPerson::class, 'person_id');
    }

    public function crmProducts(): HasMany
    {
        return $this->hasMany(InkoopInvoiceItemCrmProduct::class, 'inkoop_invoice_item_id');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'inkoop_invoice_item_crm_products', 'inkoop_invoice_item_id', 'product_id')
            ->withTimestamps();
    }

    public function getCrmIdAttribute(): string
    {
        return $this->crmProducts->pluck('crm_id')->filter()->implode(',');
    }

    public function getCrmStatusAttribute(): string
    {
        return $this->crmProducts->first()?->crm_status ?? 'afgerond';
    }
}
