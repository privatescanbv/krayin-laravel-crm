<?php

namespace Webkul\Lead\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Webkul\Lead\Contracts\Type as TypeContract;

class Type extends Model implements TypeContract
{
    protected $table = 'lead_types';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
    ];

    /**
     * Get the leads.
     */
    public function leads(): HasMany
    {
        return $this->hasMany(LeadProxy::modelClass());
    }
}
