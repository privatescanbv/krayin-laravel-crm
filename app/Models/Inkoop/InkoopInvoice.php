<?php

namespace App\Models\Inkoop;

use App\Enums\Inkoop\InkoopInvoiceParser;
use App\Enums\Inkoop\InkoopInvoiceStatus;
use App\Models\Clinic;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InkoopInvoice extends Model
{
    use HasFactory;

    protected $table = 'inkoop_invoices';

    protected $fillable = [
        'clinic_id',
        'invoice_number',
        'invoice_date',
        'total_amount',
        'pdf_path',
        'filename',
        'name',
        'reference_date',
        'parser',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'invoice_date'   => 'date',
        'reference_date' => 'date',
        'total_amount'   => 'decimal:2',
        'status'         => InkoopInvoiceStatus::class,
        'parser'         => InkoopInvoiceParser::class,
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

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function persons(): HasMany
    {
        return $this->hasMany(InkoopPerson::class, 'invoice_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InkoopInvoiceItem::class, 'inkoop_invoice_id');
    }

    public function invoiceItems(): HasMany
    {
        return $this->items();
    }

    public function calculateResolvedInvoiceItemsPercentage(): int
    {
        $items = $this->invoiceItems()->with('crmProducts')->get();

        if ($items->isEmpty()) {
            return 0;
        }

        $resolvedItems = $items->filter(fn (InkoopInvoiceItem $item) => $item->crmProducts->isNotEmpty())->count();

        return (int) round(($resolvedItems / $items->count()) * 100);
    }

    public function getSupplierTypeAttribute(): ?string
    {
        return $this->parser?->supplierType();
    }
}
