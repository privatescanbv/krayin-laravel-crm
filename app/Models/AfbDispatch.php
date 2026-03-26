<?php

namespace App\Models;

use App\Enums\AfbDispatchStatus;
use App\Enums\AfbDispatchType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Webkul\Email\Models\Email;

/** One email send to a clinic department; PDFs per patient are {@see AfbPersonDocument} rows. */
class AfbDispatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'clinic_id',
        'clinic_department_id',
        'email_id',
        'type',
        'status',
        'sent_at',
        'last_attempt_at',
        'attempt',
        'error_message',
    ];

    protected $casts = [
        'clinic_id'            => 'integer',
        'clinic_department_id' => 'integer',
        'email_id'             => 'integer',
        'type'                 => AfbDispatchType::class,
        'status'               => AfbDispatchStatus::class,
        'sent_at'              => 'datetime',
        'last_attempt_at'      => 'datetime',
        'attempt'              => 'integer',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function clinicDepartment(): BelongsTo
    {
        return $this->belongsTo(ClinicDepartment::class);
    }

    public function email(): BelongsTo
    {
        return $this->belongsTo(Email::class);
    }

    public function personDocuments(): HasMany
    {
        return $this->hasMany(AfbPersonDocument::class, 'afb_dispatch_id');
    }
}
