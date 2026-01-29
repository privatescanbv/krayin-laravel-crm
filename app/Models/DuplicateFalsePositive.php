<?php

namespace App\Models;

use App\Enums\DuplicateEntityType;
use App\Traits\HasAuditTrail;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperDuplicateFalsePositive
 */
class DuplicateFalsePositive extends Model
{
    use HasAuditTrail;

    public $timestamps = false;

    protected $table = 'duplicates_false_positives';

    protected $fillable = [
        'entity_type',
        'entity_id_1',
        'entity_id_2',
        'reason',
    ];

    protected $casts = [
        'entity_type' => DuplicateEntityType::class,
        'entity_id_1' => 'integer',
        'entity_id_2' => 'integer',
    ];
}
