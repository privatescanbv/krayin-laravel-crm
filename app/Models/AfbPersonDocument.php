<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Webkul\Contact\Models\Person;

/**
 * One generated AFB PDF for a patient, linked to a CRM order and parent dispatch (email run).
 */
class AfbPersonDocument extends Model
{
    use HasFactory;

    protected $table = 'afb_person_documents';

    protected $fillable = [
        'afb_dispatch_id',
        'order_id',
        'person_id',
        'patient_name',
        'file_name',
        'file_path',
        'sent_at',
    ];

    protected $casts = [
        'afb_dispatch_id' => 'integer',
        'order_id'        => 'integer',
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

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
