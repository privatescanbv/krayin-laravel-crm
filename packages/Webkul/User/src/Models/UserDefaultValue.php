<?php

namespace Webkul\User\Models;

use Illuminate\Database\Eloquent\Model;

class UserDefaultValue extends Model
{
    protected $table = 'user_default_values';

    protected $fillable = [
        'user_id',
        'key',
        'value',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

