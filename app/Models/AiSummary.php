<?php

namespace App\Models;

use Database\Factories\AiSummaryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AiSummary extends Model
{
    /** @use HasFactory<AiSummaryFactory> */
    use HasFactory;

    /** Statuses in which a generation is already on its way; a new dispatch would be a no-op. */
    public const PENDING_STATUSES = ['queued', 'processing', 'retrying'];

    protected $fillable = [
        'subject_type',
        'subject_id',
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

    protected static function newFactory(): AiSummaryFactory
    {
        return AiSummaryFactory::new();
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function generations(): HasMany
    {
        return $this->hasMany(AiSummaryGeneration::class);
    }

    public function isPending(): bool
    {
        return in_array($this->status, self::PENDING_STATUSES, true);
    }
}
