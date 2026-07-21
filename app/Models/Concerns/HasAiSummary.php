<?php

namespace App\Models\Concerns;

use App\Models\AiFeedback;
use App\Models\AiSummary;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * Gives a model an AI summary, its generation history and the manual corrections
 * sales colleagues left on it. Registering the model in config/ai_summaries.php is
 * what actually makes summaries generate; this trait only wires up the relations.
 */
trait HasAiSummary
{
    public function aiSummary(): MorphOne
    {
        return $this->morphOne(AiSummary::class, 'subject');
    }

    public function aiFeedback(): MorphMany
    {
        return $this->morphMany(AiFeedback::class, 'subject');
    }
}
