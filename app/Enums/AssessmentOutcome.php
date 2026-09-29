<?php

namespace App\Enums;

/**
 * Outcome of the Herniapoli doctor's assessment on a sale ("Uitkomst beoordeling").
 *
 * Values are stored in salesleads.assessment_outcome and read by the analytics sync
 * (database/analytics/003_sync_procedure.sql, fact_hernia_traject): keep them stable.
 */
enum AssessmentOutcome: string
{
    case Pted1 = 'pted_1';
    case Pted2 = 'pted_2';
    case Micro1 = 'micro_1';
    case Micro2 = 'micro_2';
    case Micro3 = 'micro_3';
    case Micro4 = 'micro_4';
    case Acdf1 = 'acdf_1';
    case Acdf2 = 'acdf_2';
    case Tlif1 = 'tlif_1';
    case Tlif2 = 'tlif_2';
    case NoSurgery = 'geen_op_indicatie';
    case Injections = 'injecties_infiltraties';

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

    public function label(): string
    {
        return match ($this) {
            self::Pted1      => 'PTED 1 niv.',
            self::Pted2      => 'PTED 2 niv.',
            self::Micro1     => 'Mikro 1 niv.',
            self::Micro2     => 'Mikro 2 niv.',
            self::Micro3     => 'Mikro 3 niv.',
            self::Micro4     => 'Mikro 4 niv.',
            self::Acdf1      => 'ACDF 1 niv.',
            self::Acdf2      => 'ACDF 2 niv.',
            self::Tlif1      => 'TLIF 1 niv.',
            self::Tlif2      => 'TLIF 2 niv.',
            self::NoSurgery  => 'Kein OP indikation',
            self::Injections => 'Injecties/Infiltraties',
        };
    }

    public function isSurgeryAdvice(): bool
    {
        return ! in_array($this, [self::NoSurgery, self::Injections], true);
    }
}
