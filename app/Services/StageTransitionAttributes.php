<?php

namespace App\Services;

use App\Models\AssessmentOutcome;
use App\Models\SalesLead;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;
use Throwable;

class StageTransitionAttributes
{
    /**
     * Resolve closed_at for a stage transition.
     * Returns Carbon when stage is won/lost (defaults to now if request value empty).
     * Returns null when stage is neither won nor lost (reset).
     *
     * @param  object  $stage  Stage model with is_won, is_lost
     * @param  string|null  $requestClosedAt  Request value (typically d-m-Y from frontend)
     */
    public static function resolveClosedAt(object $stage, ?string $requestClosedAt): ?Carbon
    {
        if (! static::isWonOrLost($stage)) {
            return null;
        }

        if ($requestClosedAt) {
            try {
                return Carbon::createFromFormat('d-m-Y', $requestClosedAt)->startOfDay();
            } catch (Throwable $e) {
                return Carbon::parse($requestClosedAt);
            }
        }

        return now();
    }

    /**
     * Resolve lost_reason for a stage transition.
     * Returns request value when stage is lost, null otherwise.
     *
     * @param  object  $stage  Stage model with is_lost
     */
    public static function resolveLostReason(object $stage, ?string $requestLostReason): ?string
    {
        return $stage->is_lost ? $requestLostReason : null;
    }

    /**
     * Whether the stage is won or lost.
     */
    public static function isWonOrLost(object $stage): bool
    {
        return (bool) ($stage->is_won ?? false) || (bool) ($stage->is_lost ?? false);
    }

    /**
     * Herniapoli: from "Beoordeling gereed" onwards the assessment outcome is required
     * (AssessmentOutcome::requiredForStageCodes). Only checked on an actual stage change, so
     * existing sales without an outcome can still be edited. When additional research is still
     * required (only allowed up to "Gepland voor aanvullend onderzoek") the outcome may stay empty.
     *
     * @throws ValidationException
     */
    public static function ensureAssessmentOutcome(SalesLead $salesLead, mixed $targetStage, mixed $outcome, bool $additionalResearch = false): void
    {
        if (! $targetStage
            || (int) $targetStage->id === (int) $salesLead->pipeline_stage_id
            || ! in_array($targetStage->code, AssessmentOutcome::requiredForStageCodes(), true)
            || $additionalResearch
            || ! blank($outcome)) {
            return;
        }

        throw ValidationException::withMessages([
            'assessment_outcome' => "Vul eerst de uitkomst beoordeling in voordat de sales naar \"{$targetStage->name}\" gaat.",
        ]);
    }

    /**
     * "Aanvullend onderzoek vereist" as requested (null = keep the current value), forced to nee once
     * the stage is past "Gepland voor aanvullend onderzoek" (AssessmentOutcome::additionalResearchAllowedStageCodes).
     */
    public static function resolveAdditionalResearch(SalesLead $salesLead, mixed $stage, ?bool $requested = null): bool
    {
        $requested ??= (bool) $salesLead->additional_research_required;

        $pastAllowedStages = $stage
            && in_array($stage->code, AssessmentOutcome::requiredForStageCodes(), true)
            && ! in_array($stage->code, AssessmentOutcome::additionalResearchAllowedStageCodes(), true);

        return $requested && ! $pastAllowedStages;
    }
}
