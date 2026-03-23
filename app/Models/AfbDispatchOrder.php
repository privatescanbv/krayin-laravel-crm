<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Webkul\Contact\Models\Person;

class AfbDispatchOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'afb_dispatch_id',
        'order_id',
        'clinic_id',
        'person_id',
        'patient_name',
        'file_name',
        'file_path',
        'sent_at',
    ];

    protected $casts = [
        'afb_dispatch_id' => 'integer',
        'order_id'        => 'integer',
        'clinic_id'       => 'integer',
        'person_id'       => 'integer',
        'sent_at'         => 'datetime',
    ];

    public function dispatch(): BelongsTo
    {
        return $this->belongsTo(AfbDispatch::class, 'afb_dispatch_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
