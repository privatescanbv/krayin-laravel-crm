<?php

namespace App\Models\Inkoop;

use App\Models\Clinic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Webkul\Product\Models\Product;

/**
 * relation between scanned invoice items and CRM products, to link them together
 */
class InkoopInvoiceItemCrmProduct extends Model
{
    protected $table = 'inkoop_invoice_item_crm_products';

    protected $fillable = [
        'clinic_id',
        'inkoop_invoice_item_id',
        'invoice_item_id',
        'product_id',
        'crm_id',
        'crm_status',
        'purchase_price',
    ];

    protected $casts = [
        'purchase_price' => 'decimal:2',
    ];

    public function setInvoiceItemIdAttribute($value): void
    {
        $this->attributes['inkoop_invoice_item_id'] = $value;
    }

    public function getInvoiceItemIdAttribute()
    {
        return $this->attributes['inkoop_invoice_item_id'] ?? null;
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function invoiceItem(): BelongsTo
    {
        return $this->belongsTo(InkoopInvoiceItem::class, 'inkoop_invoice_item_id');
    }

    public function item(): BelongsTo
    {
        return $this->invoiceItem();
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
