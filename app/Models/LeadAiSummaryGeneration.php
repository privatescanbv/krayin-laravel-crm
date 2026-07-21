<?php

namespace App\Models;

use Database\Factories\LeadAiSummaryGenerationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Webkul\Lead\Models\Lead;

class LeadAiSummaryGeneration extends Model
{
    /** @use HasFactory<LeadAiSummaryGenerationFactory> */
    use HasFactory;

    protected $fillable = [
        'lead_id',
        'lead_ai_summary_id',
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
        'context_snapshot'  => 'array',
        'raw_response'      => 'encrypted',
        'parsed_response'   => 'array',
        'started_at'        => 'datetime',
        'completed_at'      => 'datetime',
    ];

    protected static function newFactory(): LeadAiSummaryGenerationFactory
    {
        return LeadAiSummaryGenerationFactory::new();
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function summary(): BelongsTo
    {
        return $this->belongsTo(LeadAiSummary::class, 'lead_ai_summary_id');
    }
}
