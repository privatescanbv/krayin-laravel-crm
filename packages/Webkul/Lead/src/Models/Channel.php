<?php

namespace Webkul\Lead\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Webkul\Lead\Contracts\Channel as ChannelContract;

class Channel extends Model implements ChannelContract
{
    protected $table = 'lead_channels';

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
