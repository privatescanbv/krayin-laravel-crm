<?php

namespace App\Models;

use Database\Factories\AiSummaryGenerationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AiSummaryGeneration extends Model
{
    /** @use HasFactory<AiSummaryGenerationFactory> */
    use HasFactory;

    protected $fillable = [
        'subject_type',
        'subject_id',
        'ai_summary_id',
        'status',
        'input_hash',
        'context_snapshot',
        'raw_response',
        'parsed_response',
        'model',
        'prompt_version',
        'tokens_input',
        'tokens_output',
        'duration_ms',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'context_snapshot' => 'array',
        'raw_response'     => 'encrypted',
        'parsed_response'  => 'array',
        'started_at'       => 'datetime',
        'completed_at'     => 'datetime',
    ];

    protected static function newFactory(): AiSummaryGenerationFactory
    {
        return AiSummaryGenerationFactory::new();
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function summary(): BelongsTo
    {
        return $this->belongsTo(AiSummary::class, 'ai_summary_id');
    }
}
