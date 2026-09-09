<?php

namespace App\Services\Concerns;

use App\Enums\PipelineStage;

/**
 * Helper voor het registreren van status transitie regels.
 *
 * Verwacht dat de consumer klasse de volgende statische properties definieert:
 * - private static array $transitionRules = [];
 * - private static bool $defaultsInitialized = false;
 */
trait HasStatusTransitionRules
{
    /**
     * Registreer een specifieke transitie regel.
     */
    public static function addTransitionRule(PipelineStage $fromStageCode, PipelineStage $toStageCode, array $rules): void
    {
        $transitionKey = $fromStageCode->value.'->'.$toStageCode->value;

        static::$transitionRules[$transitionKey] = $rules;
    }

    /**
     * Registreer dezelfde regel voor meerdere doel-stages, vanuit één bron-stage.
     *
     * @param  PipelineStage[]  $toStageCodes
     */
    public static function addTransitionsRule(PipelineStage $fromStageCode, array $toStageCodes, array $rules): void
    {
        foreach ($toStageCodes as $toStageCode) {
            static::addTransitionRule($fromStageCode, $toStageCode, $rules);
        }
    }

    /**
     * Registreer dezelfde regel voor meerdere combinaties van bron- en doel-stages.
     *
     * @param  PipelineStage[]  $fromStageCodes
     * @param  PipelineStage[]  $toStageCodes
     */
    public static function addTransitionsRules(array $fromStageCodes, array $toStageCodes, array $rules): void
    {
        foreach ($fromStageCodes as $fromStageCode) {
            static::addTransitionsRule($fromStageCode, $toStageCodes, $rules);
        }
    }

    /**
     * Registreer wildcard regels voor alle gegeven doel-stages.
     *
     * Key formaat: "*->to_stage_code"
     *
     * @param  PipelineStage[]  $toStageCodes
     */
    public static function addWildcardToStagesRules(array $toStageCodes, array $rules): void
    {
        foreach ($toStageCodes as $toStageCode) {
            $transitionKey = '*->'.$toStageCode->value;

            static::$transitionRules[$transitionKey] = $rules;
        }
    }

    /**
     * Reset alle geregistreerde regels en initialisatie-state.
     * Handig voor tests.
     */
    public static function resetTransitionRules(): void
    {
        static::$transitionRules = [];
        static::$defaultsInitialized = false;
    }
}
