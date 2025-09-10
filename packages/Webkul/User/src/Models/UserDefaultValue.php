<?php

namespace Webkul\User\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasAuditTrail;

class UserDefaultValue extends Model
{
    use HasAuditTrail;
    protected $table = 'user_default_values';

    protected $fillable = [
        'user_id',
        'key',
        'value',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

