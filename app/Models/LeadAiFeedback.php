<?php

namespace App\Models;

use Database\Factories\LeadAiFeedbackFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Webkul\Lead\Models\Lead;
use Webkul\User\Models\User;

class LeadAiFeedback extends Model
{
    /** @use HasFactory<LeadAiFeedbackFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $table = 'lead_ai_feedback';

    protected $fillable = [
        'lead_id',
        'user_id',
        'feedback',
        'is_active',
        'included_in_generation_at',
    ];

    protected $casts = [
        'is_active'                    => 'boolean',
        'included_in_generation_at'    => 'datetime',
    ];

    protected static function newFactory(): LeadAiFeedbackFactory
    {
        return LeadAiFeedbackFactory::new();
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
