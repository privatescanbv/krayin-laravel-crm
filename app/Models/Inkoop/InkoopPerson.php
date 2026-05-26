<?php

namespace App\Models\Inkoop;

use App\Models\Clinic;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InkoopPerson extends Model
{
    use HasFactory;

    protected $table = 'inkoop_persons';

    protected $fillable = [
        'clinic_id',
        'invoice_id',
        'name',
        'external_id',
        'firstname',
        'lastname',
        'birthday',
        'crm_id',
    ];

    protected $casts = [
        'birthday' => 'date',
    ];

    protected $appends = ['producten'];

    public static function calculatePercentageHasCRMRelation(string|int $invoiceId): int
    {
        $totalPatients = self::where('invoice_id', $invoiceId)->count();
        $patientsWithCrmRelation = self::where('invoice_id', $invoiceId)->whereNotNull('crm_id')->count();

        return $totalPatients > 0 ? (int) round(($patientsWithCrmRelation / $totalPatients) * 100) : 0;
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(InkoopInvoice::class, 'invoice_id');
    }

    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InkoopInvoiceItem::class, 'person_id');
    }

    public function getProductenAttribute()
    {
        return $this->invoiceItems;
    }
}
