<?php

namespace Webkul\Lead\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Webkul\Lead\Contracts\Source as SourceContract;

class Source extends Model implements SourceContract
{
    protected $table = 'lead_sources';

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
