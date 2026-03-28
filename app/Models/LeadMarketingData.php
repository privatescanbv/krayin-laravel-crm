<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Webkul\Lead\Models\Lead;

class LeadMarketingData extends Model
{
    protected $table = 'lead_marketing_data';

    protected $fillable = [
        'lead_id',
        'key',
        'value',
    ];

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }
}
