<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Webkul\Admin\Http\Controllers\Contact\Persons\PersonController;
use Webkul\Contact\Models\Person;
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

        // Check for specific transition rules first
        if (isset(self::$transitionRules[$transitionKey])) {
            $rules = self::$transitionRules[$transitionKey];
        }
        // Check for wildcard rules (e.g., *->gewonnen)
        elseif (isset(self::$transitionRules['*->'.$newStage->code])) {
            $rules = self::$transitionRules['*->'.$newStage->code];
        } else {
            return; // Geen validatie regels voor deze transitie
        }

        $errors = [];

        // Valideer minimum aantal personen
        if (isset($rules['min_persons'])) {
            $personCount = $lead->persons()->count();
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
     * Calculate match score between lead and person.
     * This is a simplified version of the match score calculation from PersonController.
     */
    public static function calculateMatchScore(Lead $lead, Person $person): float
    {
        $controller = app(PersonController::class);

        return $controller->calculateMatchScore($lead, $person);
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

        // Add validation for transitions to "gewonnen" and "verloren" statuses
        self::addTransitionsRule(
            '*', // Any stage can transition to won/lost
            ['won', 'lost', 'won-hernia', 'lost-hernia'],
            [
                'custom_validation' => function (Lead $lead) {
                    return self::validateWonLostTransition($lead);
                },
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

    /**
     * Validate transition to won/lost status.
     * Requires at least 1 person (contact person or linked persons).
     * Requires that at least one person (contact person or linked) has a 100% match score.
     */
    private static function validateWonLostTransition(Lead $lead): array
    {
        $errors = [];

        // Get all persons (contact person and linked persons) as a single collection
        $allPersons = $lead->getContactAndPersons();

        // Check if lead has at least 1 person
        if ($allPersons->isEmpty()) {
            $errors[] = 'Een lead mag alleen naar status "gewonnen" of "verloren" als er minimaal 1 persoon aan gekoppeld is (contact person of gekoppelde personen).';

            return $errors;
        }

        // Check if at least one person has 100% match score
        $maxScore = 0;
        $hasPerfectMatch = false;

        foreach ($allPersons as $person) {
            $personScore = self::calculateMatchScore($lead, $person);
            $maxScore = max($maxScore, $personScore);

            if ($personScore >= 100) {
                $hasPerfectMatch = true;
                break; // Found a perfect match, no need to continue
            }
        }

        if (! $hasPerfectMatch) {
            $errors[] = 'Een lead mag alleen naar status "gewonnen" of "verloren" als de contact person of een van de gekoppelde personen een match score van 100% heeft. Hoogste match score: '.round($maxScore, 1).'%';
        }

        return $errors;
    }
}
