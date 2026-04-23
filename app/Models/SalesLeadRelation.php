<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperSalesLeadRelation
 */
class SalesLeadRelation extends Model
{
    protected $table = 'saleslead_relations';

    protected $fillable = [
        'source_saleslead_id',
        'target_saleslead_id',
        'relation_type',
    ];

    public function sourceSalesLead(): BelongsTo
    {
        return $this->belongsTo(SalesLead::class, 'source_saleslead_id');
    }

    public function targetSalesLead(): BelongsTo
    {
        return $this->belongsTo(SalesLead::class, 'target_saleslead_id');
    }
}
