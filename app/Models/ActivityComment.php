<?php

namespace App\Models;

use App\Traits\HasAuditTrail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Webkul\Activity\Models\Activity;

class ActivityComment extends Model
{
    use HasAuditTrail;

    protected $table = 'activity_comments';

    protected $fillable = [
        'activity_id',
        'comment',
        'created_by',
        'updated_by',
    ];

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class, 'activity_id');
    }
}
