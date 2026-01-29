<?php

namespace App\Models;

use App\Enums\PatientMessageSenderType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Webkul\Activity\Models\Activity;
use Webkul\Contact\Models\PersonProxy;
use Webkul\User\Models\UserProxy;

/**
 * @mixin IdeHelperPatientMessage
 */
class PatientMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'person_id',
        'sender_type',
        'sender_id',
        'body',
        'is_read',
        'activity_id',
    ];

    protected $casts = [
        'sender_type' => PatientMessageSenderType::class,
        'is_read'     => 'boolean',
    ];

    public function person()
    {
        return $this->belongsTo(PersonProxy::modelClass());
    }

    public function sender()
    {
        return $this->belongsTo(UserProxy::modelClass(), 'sender_id');
    }

    public function activity()
    {
        return $this->belongsTo(Activity::class, 'activity_id');
    }
}
