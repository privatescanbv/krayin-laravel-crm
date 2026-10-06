<?php

namespace App\Models;

use App\Enums\PipelineStage;
use App\Traits\HasAuditTrail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Outcome of the Herniapoli doctor's assessment on a sale ("Uitkomst beoordeling"), managed in settings.
 *
 * `code` is stored in salesleads.assessment_outcome and joined by the analytics sync
 * (database/analytics/003_sync_procedure.sql, fact_hernia_traject): it is immutable after creation.
 *
 * @mixin IdeHelperAssessmentOutcome
 */
class AssessmentOutcome extends Model
{
    use HasAuditTrail, HasFactory;

    protected $fillable = [
        'code',
        'label',
        'is_surgery_advice',
        'sort_order',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_surgery_advice' => 'boolean',
        'sort_order'        => 'integer',
        'created_by'        => 'integer',
        'updated_by'        => 'integer',
    ];

    /**
     * Hernia sales stages from "Beoordeling gereed" onwards (excluding won/lost): entering one
     * of these requires an assessment outcome.
     *
     * @return list<string>
     */
    public static function requiredForStageCodes(): array
    {
        return array_map(fn (PipelineStage $stage) => $stage->value, [
            PipelineStage::SALES_ASSESSMENT_DONE_HERNIA,
            PipelineStage::SALES_PATIENT_REFLECTION_TIME_HERNIA,
            PipelineStage::SALES_PLANNED_FOR_ADDITIONAL_RESEARCH_HERNIA,
            PipelineStage::SALES_WAIT_HEALTH_INSURER_HERNIA,
            PipelineStage::SALES_CONFIRM_TREATMENT_HERNIA,
            PipelineStage::SALES_TREATMENT_PLANNED_HERNIA,
            PipelineStage::SALES_AFTERCARE1_HERNIA,
            PipelineStage::SALES_AFTERCARE2_HERNIA,
            PipelineStage::SALES_PHYSICAL_CONSULTATION_HERNIA,
        ]);
    }

    /**
     * Hernia sales stages up to and including "Gepland voor aanvullend onderzoek": here
     * "aanvullend onderzoek vereist" may be ja, and then the outcome may still be empty.
     * In later stages the flag is always nee and the outcome is required.
     *
     * @return list<string>
     */
    public static function additionalResearchAllowedStageCodes(): array
    {
        return array_map(fn (PipelineStage $stage) => $stage->value, [
            PipelineStage::SALES_ASSESSMENT_DONE_HERNIA,
            PipelineStage::SALES_PATIENT_REFLECTION_TIME_HERNIA,
            PipelineStage::SALES_PLANNED_FOR_ADDITIONAL_RESEARCH_HERNIA,
        ]);
    }

    public static function labelFor(?string $code): ?string
    {
        return $code === null ? null : static::where('code', $code)->value('label');
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('label');
    }

    public function isInUse(): bool
    {
        return SalesLead::where('assessment_outcome', $this->code)->exists();
    }
}
