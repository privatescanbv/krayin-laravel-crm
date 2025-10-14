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

        // Check for specific transition rules first
        if (isset(self::$transitionRules[$transitionKey])) {
            $rules = self::$transitionRules[$transitionKey];
        } 
        // Check for wildcard rules (e.g., *->gewonnen)
        elseif (isset(self::$transitionRules['*->'.$newStage->code])) {
            $rules = self::$transitionRules['*->'.$newStage->code];
        }
        else {
            return; // Geen validatie regels voor deze transitie
        }

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

        // Add validation for transitions to "gewonnen" and "verloren" statuses
        self::addTransitionsRule(
            '*', // Any stage can transition to won/lost
            ['gewonnen', 'verloren'],
            [
                'custom_validation' => function (Lead $lead) {
                    return self::validateWonLostTransition($lead);
                }
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
     * Requires exactly 1 person with 100% match score.
     */
    private static function validateWonLostTransition(Lead $lead): array
    {
        $errors = [];

        // Check if lead has exactly 1 person
        $personCount = $lead->persons()->count();
        if ($personCount !== 1) {
            $errors[] = 'Een lead mag alleen naar status "gewonnen" of "verloren" als er precies 1 persoon aan gekoppeld is. Huidige aantal personen: ' . $personCount;
            return $errors;
        }

        // Get the single person
        $person = $lead->persons()->first();
        if (!$person) {
            $errors[] = 'Geen persoon gevonden voor deze lead.';
            return $errors;
        }

        // Calculate match score between lead and person
        $matchScore = self::calculateMatchScore($lead, $person);
        
        if ($matchScore < 100) {
            $errors[] = 'Een lead mag alleen naar status "gewonnen" of "verloren" als de contact match score 100% is. Huidige match score: ' . round($matchScore, 1) . '%';
        }

        return $errors;
    }

    /**
     * Calculate match score between lead and person.
     * This is a simplified version of the match score calculation from PersonController.
     */
    private static function calculateMatchScore(Lead $lead, \Webkul\Contact\Models\Person $person): float
    {
        $score = 0.0;
        $maxScore = 100.0;

        // Calculate name field matches (85% weight)
        $nameScore = self::calculateNameMatchScore($lead, $person);
        $score += $nameScore * 0.85 * 100;

        // Email matching (5% weight)
        $emailScore = self::calculateEmailMatchScore($lead, $person);
        $score += $emailScore * 0.05 * 100;

        // Phone number matching (5% weight)
        $phoneScore = self::calculatePhoneMatchScore($lead, $person);
        $score += $phoneScore * 0.05 * 100;

        // Address matching (5% weight)
        $addressScore = self::calculateAddressMatchScore($lead, $person);
        $score += $addressScore * 0.05 * 100;

        return min($score, $maxScore);
    }

    /**
     * Calculate name match score between lead and person.
     */
    private static function calculateNameMatchScore(Lead $lead, \Webkul\Contact\Models\Person $person): float
    {
        $nameFields = [
            'first_name',
            'last_name',
            'lastname_prefix',
            'married_name',
            'married_name_prefix',
            'initials',
            'date_of_birth'
        ];

        $totalMatches = 0;
        $totalPossibleMatches = 0;

        foreach ($nameFields as $field) {
            $leadValue = $lead->$field ?? '';
            $personValue = $person->$field ?? '';

            if (!empty($leadValue) || !empty($personValue)) {
                $totalPossibleMatches++;

                // Handle matching logic
                $isMatch = false;

                // If both values are empty, treat as match
                if (empty($leadValue) && empty($personValue)) {
                    $isMatch = true;
                }
                // If both values exist, compare them
                elseif (!empty($leadValue) && !empty($personValue)) {
                    // Special handling for date_of_birth
                    if ($field === 'date_of_birth') {
                        $leadDate = self::formatDateForComparison($leadValue);
                        $personDate = self::formatDateForComparison($personValue);
                        if ($leadDate && $personDate && $leadDate === $personDate) {
                            $isMatch = true;
                        }
                    }
                    // Exact match for other fields
                    elseif (strtolower(trim($leadValue)) === strtolower(trim($personValue))) {
                        $isMatch = true;
                    }
                    // Partial match for names (not for initials or date_of_birth)
                    elseif (!in_array($field, ['initials', 'date_of_birth']) &&
                        (stripos($personValue, $leadValue) !== false ||
                            stripos($leadValue, $personValue) !== false)) {
                        $isMatch = true;
                    }
                }

                if ($isMatch) {
                    $totalMatches++;
                }
            }
        }

        // Calculate scores based on criteria
        if ($totalPossibleMatches === 0) {
            return 0.0;
        }

        $totalMatchRatio = $totalMatches / $totalPossibleMatches;

        // 100% match on all name fields = 100% score
        if ($totalMatchRatio >= 1.0) {
            return 1.0;
        }

        // Partial scoring based on match ratio
        return $totalMatchRatio * 0.80;
    }

    /**
     * Calculate email match score between lead and person.
     */
    private static function calculateEmailMatchScore(Lead $lead, \Webkul\Contact\Models\Person $person): float
    {
        $leadEmails = self::extractEmails($lead);
        $personEmails = self::extractEmails($person);

        // If both are empty, treat as perfect match (100%)
        if (empty($leadEmails) && empty($personEmails)) {
            return 1.0;
        }

        // If only one is empty, no match
        if (empty($leadEmails) || empty($personEmails)) {
            return 0.0;
        }

        $matchCount = 0;
        $totalPersonEmails = count($personEmails);

        foreach ($leadEmails as $leadEmail) {
            foreach ($personEmails as $personEmail) {
                if (strtolower($leadEmail) === strtolower($personEmail)) {
                    $matchCount++;
                    break; // Don't count the same lead email multiple times
                }
            }
        }

        return $matchCount > 0 ? ($matchCount / $totalPersonEmails) : 0.0;
    }

    /**
     * Calculate phone match score between lead and person.
     */
    private static function calculatePhoneMatchScore(Lead $lead, \Webkul\Contact\Models\Person $person): float
    {
        $leadPhones = self::extractPhones($lead);
        $personPhones = self::extractPhones($person);

        // If both are empty, treat as perfect match (100%)
        if (empty($leadPhones) && empty($personPhones)) {
            return 1.0;
        }

        // If only one is empty, no match
        if (empty($leadPhones) || empty($personPhones)) {
            return 0.0;
        }

        $matchCount = 0;
        $totalPersonPhones = count($personPhones);

        foreach ($leadPhones as $leadPhone) {
            foreach ($personPhones as $personPhone) {
                if (self::normalizePhoneNumber($leadPhone) === self::normalizePhoneNumber($personPhone)) {
                    $matchCount++;
                    break; // Don't count the same lead phone multiple times
                }
            }
        }

        return $matchCount > 0 ? ($matchCount / $totalPersonPhones) : 0.0;
    }

    /**
     * Calculate address match score between lead and person.
     */
    private static function calculateAddressMatchScore(Lead $lead, \Webkul\Contact\Models\Person $person): float
    {
        $leadAddress = self::extractAddressData($lead);
        $personAddress = self::extractAddressData($person);

        // If both addresses are empty, treat as perfect match
        if (empty($leadAddress) && empty($personAddress)) {
            return 1.0;
        }

        // If only one address is empty, no match
        if (empty($leadAddress) || empty($personAddress)) {
            return 0.0;
        }

        $addressFields = ['street', 'house_number', 'city', 'postal_code', 'country'];
        $matchCount = 0;
        $totalFields = 0;

        foreach ($addressFields as $field) {
            $leadValue = $leadAddress[$field] ?? '';
            $personValue = $personAddress[$field] ?? '';

            if (!empty($leadValue) || !empty($personValue)) {
                $totalFields++;

                if (!empty($leadValue) && !empty($personValue)) {
                    // Normalize and compare
                    $leadNormalized = strtolower(trim($leadValue));
                    $personNormalized = strtolower(trim($personValue));

                    if ($leadNormalized === $personNormalized) {
                        $matchCount++;
                    }
                    // For postal codes, also check partial matches
                    elseif ($field === 'postal_code' &&
                           (strpos($leadNormalized, $personNormalized) !== false ||
                            strpos($personNormalized, $leadNormalized) !== false)) {
                        $matchCount += 0.5; // Partial match
                    }
                }
            }
        }

        return $totalFields > 0 ? ($matchCount / $totalFields) : 0.0;
    }

    /**
     * Extract emails from lead or person.
     */
    private static function extractEmails($entity): array
    {
        $emails = [];

        // Handle array format (from emails field)
        if (!empty($entity->emails) && is_array($entity->emails)) {
            foreach ($entity->emails as $email) {
                if (is_array($email) && !empty($email['value'])) {
                    $emails[] = $email['value'];
                } elseif (is_string($email)) {
                    $emails[] = $email;
                }
            }
        }

        // Handle single email field (if exists)
        if (!empty($entity->email)) {
            $emails[] = $entity->email;
        }

        return array_filter($emails);
    }

    /**
     * Extract phone numbers from lead or person.
     */
    private static function extractPhones($entity): array
    {
        $phones = [];

        // Handle array format (from phones or contact_numbers field)
        if (!empty($entity->phones) && is_array($entity->phones)) {
            foreach ($entity->phones as $phone) {
                if (is_array($phone) && !empty($phone['value'])) {
                    $phones[] = $phone['value'];
                } elseif (is_string($phone)) {
                    $phones[] = $phone;
                }
            }
        }

        // Handle single phone field (if exists)
        if (!empty($entity->phone)) {
            $phones[] = $entity->phone;
        }

        return array_filter($phones);
    }

    /**
     * Extract address data from lead or person.
     */
    private static function extractAddressData($entity): array
    {
        $address = [];

        // For both persons and leads, check if they have an address relationship
        if (method_exists($entity, 'address') && $entity->address) {
            $address = [
                'street' => $entity->address->street ?? '',
                'house_number' => $entity->address->house_number ?? '',
                'city' => $entity->address->city ?? '',
                'postal_code' => $entity->address->postal_code ?? '',
                'country' => $entity->address->country ?? '',
            ];
        }
        // Fallback to direct address fields (for backwards compatibility)
        else {
            $address = [
                'street' => $entity->street ?? '',
                'house_number' => $entity->house_number ?? '',
                'city' => $entity->city ?? '',
                'postal_code' => $entity->postal_code ?? '',
                'country' => $entity->country ?? '',
            ];
        }

        // Filter out empty values
        return array_filter($address, function($value) {
            return !empty(trim($value));
        });
    }

    /**
     * Normalize phone number for comparison.
     */
    private static function normalizePhoneNumber(string $phone): string
    {
        // Remove all non-numeric characters
        $normalized = preg_replace('/[^0-9]/', '', $phone);

        // Handle Dutch phone numbers - convert +31 to 0
        if (str_starts_with($normalized, '31') && strlen($normalized) >= 10) {
            $normalized = '0' . substr($normalized, 2);
        }

        return $normalized;
    }

    /**
     * Format date for comparison, handling invalid dates.
     */
    private static function formatDateForComparison($date): ?string
    {
        if (empty($date)) {
            return null;
        }

        try {
            // Check if it's a valid Carbon instance
            if ($date instanceof \Carbon\Carbon) {
                // Check for invalid dates (like -0001-11-30 or 0000-00-00)
                if ($date->year <= 0 || $date->year > 2100) {
                    return null;
                }
                return $date->format('Y-m-d');
            }

            // If it's a string, try to parse it
            if (is_string($date)) {
                // Skip obviously invalid dates
                if (in_array($date, ['0000-00-00', '0000-00-00 00:00:00']) || strpos($date, '-0001') === 0) {
                    return null;
                }

                $carbonDate = \Carbon\Carbon::parse($date);
                if ($carbonDate->year <= 0 || $carbonDate->year > 2100) {
                    return null;
                }
                return $carbonDate->format('Y-m-d');
            }
        } catch (Exception $e) {
            // If parsing fails, treat as null
            return null;
        }

        return null;
    }
}
