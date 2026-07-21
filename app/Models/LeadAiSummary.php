<?php

namespace App\Models;

use Database\Factories\LeadAiSummaryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Webkul\Lead\Models\Lead;

class LeadAiSummary extends Model
{
    /** @use HasFactory<LeadAiSummaryFactory> */
    use HasFactory;

    protected $fillable = [
        'lead_id',
        'summary',
        'next_action_title',
        'next_action_reason',
        'priority',
        'highlights',
        'attention_points',
        'generated_at',
        'model',
        'prompt_version',
        'status',
        'last_error',
    ];

    protected $casts = [
        'highlights'       => 'array',
        'attention_points' => 'array',
        'generated_at'     => 'datetime',
    ];

    protected static function newFactory(): LeadAiSummaryFactory
    {
        return LeadAiSummaryFactory::new();
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function generations(): HasMany
    {
        return $this->hasMany(LeadAiSummaryGeneration::class);
    }
}
