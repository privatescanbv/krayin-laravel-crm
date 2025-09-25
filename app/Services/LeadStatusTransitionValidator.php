<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Stage;

class LeadStatusTransitionValidator
{
    /**
     * Validatie regels per status transitie.
     * Key format: "from_stage_code->to_stage_code"
     */
    private static array $transitionRules = [
        // Validatie: van nieuwe-aanvraag-kwalificeren naar klant-adviseren-start
        // Minimaal 1 persoon moet gekoppeld zijn aan de lead
        'nieuwe-aanvraag-kwalificeren->klant-adviseren-start' => [
            'min_persons' => 1,
            'message'     => 'Voor de status "Klant adviseren" moet minimaal 1 persoon aan de lead gekoppeld zijn.',
        ],
    ];

    /**
     * Valideer een status transitie voor een lead.
     *
     * @throws ValidationException
     */
    public static function validateTransition(Lead $lead, int $newStageId): void
    {
        // Avoid relying on a potentially stale Eloquent relation; read current stage by ID
        $currentStage = $lead->lead_pipeline_stage_id
            ? Stage::find($lead->lead_pipeline_stage_id)
            : null;
        $newStage = Stage::findOrFail($newStageId);

        if (! $currentStage) {
            // Als er geen huidige stage is, is het een nieuwe lead - geen transitie validatie nodig
            return;
        }

        $transitionKey = $currentStage->code.'->'.$newStage->code;

        // Controleer of er validatieregels zijn voor deze transitie
        if (! isset(self::$transitionRules[$transitionKey])) {
            return; // Geen validatie regels voor deze transitie
        }

        $rules = self::$transitionRules[$transitionKey];
        $errors = [];

        // Valideer minimum aantal personen
        if (isset($rules['min_persons'])) {
            $personCount = $lead->persons_count;
            if ($personCount < $rules['min_persons']) {
                $errors[] = $rules['message'] ?? "Minimaal {$rules['min_persons']} persoon(en) vereist voor deze status.";
            }
        }

        // Valideer verplichte velden
        if (isset($rules['required_fields'])) {
            foreach ($rules['required_fields'] as $field) {
                if (empty($lead->$field)) {
                    $errors[] = "Het veld '{$field}' is verplicht voor deze status.";
                }
            }
        }

        // Valideer custom regels
        if (isset($rules['custom_validation'])) {
            $customErrors = self::executeCustomValidation($lead, $rules['custom_validation']);
            $errors = array_merge($errors, $customErrors);
        }

        // Gooi ValidationException als er fouten zijn
        if (! empty($errors)) {
            $validator = Validator::make([], []);
            foreach ($errors as $error) {
                $validator->errors()->add('status_transition', $error);
            }
            throw new ValidationException($validator);
        }
    }

    /**
     * Voeg een nieuwe transitie validatie regel toe.
     */
    public static function addTransitionRule(string $fromStageCode, string $toStageCode, array $rules): void
    {
        $transitionKey = $fromStageCode.'->'.$toStageCode;
        self::$transitionRules[$transitionKey] = $rules;
    }

    public static function addTransitionsRule(string $fromStageCode, array $toStageCodes, array $rules): void
    {
        foreach ($toStageCodes as $toStageCode) {
            self::addTransitionRule($fromStageCode, $toStageCode, $rules);
        }
    }

    /**
     * Verwijder een transitie validatie regel.
     */
    public static function removeTransitionRule(string $fromStageCode, string $toStageCode): void
    {
        $transitionKey = $fromStageCode.'->'.$toStageCode;
        unset(self::$transitionRules[$transitionKey]);
    }

    /**
     * Krijg alle transitie regels (voor debugging/configuratie).
     */
    public static function getAllTransitionRules(): array
    {
        return self::$transitionRules;
    }

    /**
     * Controleer of een transitie validatie regels heeft.
     */
    public static function hasTransitionRule(string $fromStageCode, string $toStageCode): bool
    {
        $transitionKey = $fromStageCode.'->'.$toStageCode;

        return isset(self::$transitionRules[$transitionKey]);
    }

    /**
     * Voer custom validatie uit.
     */
    private static function executeCustomValidation(Lead $lead, callable $validationFunction): array
    {
        try {
            $result = $validationFunction($lead);

            return is_array($result) ? $result : [];
        } catch (Exception $e) {
            return ['Validatie fout: '.$e->getMessage()];
        }
    }
}
