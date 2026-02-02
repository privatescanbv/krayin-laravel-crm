<?php

namespace App\Models;

use App\Enums\NotificationReferenceType;
use App\Traits\HasAuditTrail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Webkul\Contact\Models\PersonProxy;

/**
 * @mixin IdeHelperPatientNotification
 */
class PatientNotification extends Model
{
    use HasAuditTrail, HasFactory;

    protected $table = 'patient_notifications';

    protected $fillable = [
        'patient_id',
        'type',
        'dismissable',
        'title',
        'summary',
        'reference_type',
        'reference_id',
        'read_at',
        'dismissed_at',
        'expires_at',
        'last_notified_by_email_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'dismissable'               => 'boolean',
        'reference_type'            => NotificationReferenceType::class,
        'read_at'                   => 'datetime',
        'dismissed_at'              => 'datetime',
        'expires_at'                => 'datetime',
        'last_notified_by_email_at' => 'datetime',
        'created_by'                => 'integer',
        'updated_by'                => 'integer',
    ];

    public function patient()
    {
        return $this->belongsTo(PersonProxy::modelClass(), 'patient_id');
    }

    public function scopeForPatient($query, int $patientId)
    {
        return $query->where('patient_id', $patientId);
    }

    public function scopeForMailNotification($query)
    {
        return $query->whereNull('dismissed_at')
            ->whereNull('last_notified_by_email_at');

    }
}
