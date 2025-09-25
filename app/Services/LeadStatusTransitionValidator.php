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
    private static array $transitionRules = [];

    /**
     * Indicates whether default transition rules have been registered.
     */
    private static bool $defaultsInitialized = false;

    /**
     * Valideer een status transitie voor een lead.
     *
     * @throws ValidationException
     */
    public static function validateTransition(Lead $lead, int $newStageId): void
    {
        // Lazily register default rules
        self::ensureDefaultRules();

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
            $personCount = (int) $lead->persons()->count();
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
     * Reset validator state (intended for tests).
     */
    public static function reset(): void
    {
        self::$transitionRules = [];
        self::$defaultsInitialized = false;
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

    // Removing transition rules is not supported anymore

    /**
     * Krijg alle transitie regels (voor debugging/configuratie).
     */
    public static function getAllTransitionRules(): array
    {
        self::ensureDefaultRules();

        return self::$transitionRules;
    }

    /**
     * Controleer of een transitie validatie regels heeft.
     */
    public static function hasTransitionRule(string $fromStageCode, string $toStageCode): bool
    {
        self::ensureDefaultRules();
        $transitionKey = $fromStageCode.'->'.$toStageCode;

        return isset(self::$transitionRules[$transitionKey]);
    }

    /**
     * Ensure default rules are present (lazy initialization).
     */
    private static function ensureDefaultRules(): void
    {
        if (self::$defaultsInitialized) {
            return;
        }

        // Privatescan: nieuwe-aanvraag-kwalificeren -> klant-adviseren-start
        self::addTransitionRule(
            'nieuwe-aanvraag-kwalificeren',
            'klant-adviseren-start',
            [
                'min_persons'     => 1,
                'required_fields' => ['first_name', 'last_name'],
                'message'         => 'Voor de status "Klant adviseren" moet minimaal 1 persoon aan de lead gekoppeld zijn.',
            ]
        );

        // Hernia: nieuwe-aanvraag-kwalificeren-hernia -> meerdere klant-adviseren-* doelen
        self::addTransitionsRule(
            'nieuwe-aanvraag-kwalificeren-hernia',
            [
                'klant-adviseren-start-hernia',
                'klant-adviseren-will-mri-hernia',
                'klant-adviseren-wachten-op-mri-hernia',
            ],
            [
                'min_persons'     => 1,
                'required_fields' => ['first_name', 'last_name'],
                'message'         => 'Voor de status "Klant adviseren opvolgen" moet minimaal 1 persoon aan de lead gekoppeld zijn.',
            ]

        );

        self::$defaultsInitialized = true;
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
