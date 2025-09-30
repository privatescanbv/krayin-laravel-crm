<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\ContactLabel;
use Illuminate\Http\Request;

trait NormalizesContactFields
{
    /**
     * Normalize contact fields (emails and phones) in the request
     *
     * This method:
     * - Maps single 'email'/'phone' fields to emails[]/phones[] arrays
     * - Filters out empty values
     * - Normalizes is_default to boolean
     * - Sets default label if missing
     */
    protected function normalizeContactFields(Request $request): void
    {
        $incoming = $request->all();

        logger()->info('Normalizing contact fields', $incoming);

        // Map single email/phone to array structure
        $incoming = $this->mapSingleFieldsToArrays($incoming);

        // Normalize emails array
        if (isset($incoming['emails']) && is_array($incoming['emails'])) {
            $incoming['emails'] = $this->normalizeEmailsArray($incoming['emails']);
        }

        // Normalize phones array
        if (isset($incoming['phones']) && is_array($incoming['phones'])) {
            $incoming['phones'] = $this->normalizePhonesArray($incoming['phones']);
        }

        // Normalize contact_numbers array (for backwards compatibility with Person)
        if (isset($incoming['contact_numbers']) && is_array($incoming['contact_numbers'])) {
            $incoming['contact_numbers'] = $this->normalizePhonesArray($incoming['contact_numbers']);
        }

        // Replace request data
        $request->replace($incoming);
    }

    /**
     * Map single 'email'/'phone' fields to emails[]/phones[] arrays if provided
     */
    protected function mapSingleFieldsToArrays(array $data): array
    {
        // Map single email to emails array
        if (isset($data['email']) && ! isset($data['emails'])) {
            $data['emails'] = [[
                'value'      => (string) $data['email'],
                'label'      => ContactLabel::default()->value,
                'is_default' => true,
            ]];
            unset($data['email']);
        }

        // Map single phone to phones array
        if (isset($data['phone']) && ! isset($data['phones'])) {
            $data['phones'] = [[
                'value'      => (string) $data['phone'],
                'label'      => ContactLabel::default()->value,
                'is_default' => true,
            ]];
            unset($data['phone']);
        }

        return $data;
    }

    /**
     * Normalize emails array: filter empty, normalize is_default, set default label
     */
    protected function normalizeEmailsArray(array $emails): ?array
    {
        // First normalize each email
        $emails = array_map(function ($email) {
            return $this->normalizeContactItem($email);
        }, $emails);

        // Filter out empty values
        $emails = array_values(array_filter($emails, function ($email) {
            return isset($email['value']) && trim($email['value']) !== '';
        }));

        // If no valid emails remain, return null
        if (empty($emails)) {
            return null;
        }

        // Ensure at least one is default
        $hasDefault = false;
        foreach ($emails as $email) {
            if ($email['is_default'] === true) {
                $hasDefault = true;
                break;
            }
        }

        if (! $hasDefault && count($emails) > 0) {
            $emails[0]['is_default'] = true;
        }

        return $emails;
    }

    /**
     * Normalize phones array: filter empty, normalize is_default, set default label
     */
    protected function normalizePhonesArray(array $phones): ?array
    {
        // First normalize each phone
        $phones = array_map(function ($phone) {
            return $this->normalizeContactItem($phone);
        }, $phones);

        // Filter out empty values
        $phones = array_values(array_filter($phones, function ($phone) {
            return isset($phone['value']) && trim($phone['value']) !== '';
        }));

        // If no valid phones remain, return null
        if (empty($phones)) {
            return null;
        }

        // Ensure at least one is default
        $hasDefault = false;
        foreach ($phones as $phone) {
            if ($phone['is_default'] === true) {
                $hasDefault = true;
                break;
            }
        }

        if (! $hasDefault && count($phones) > 0) {
            $phones[0]['is_default'] = true;
        }

        return $phones;
    }

    /**
     * Normalize a single contact item (email or phone)
     */
    protected function normalizeContactItem(array $item): array
    {
        // Normalize is_default to boolean
        if (isset($item['is_default'])) {
            $item['is_default'] = $this->normalizeBoolean($item['is_default']);
        } else {
            $item['is_default'] = false;
        }

        // Set default label if missing or empty
        if (! isset($item['label']) || trim($item['label']) === '') {
            $item['label'] = ContactLabel::default()->value;
        } else {
            $item['label'] = $this->normalizeLabel($item['label']);
        }

        return $item;
    }

    /**
     * Normalize various representations to boolean
     */
    protected function normalizeBoolean($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return in_array(strtolower($value), ['true', '1', 'on', 'yes']);
        }

        if (is_numeric($value)) {
            return (bool) $value;
        }

        return false;
    }

    /**
     * Normalize label to lowercase and handle common variations
     */
    protected function normalizeLabel(string $label): string
    {
        if (empty($label)) {
            return ContactLabel::default()->value;
        }

        $label = trim(strtolower($label));

        // Map common variations to standard labels
        $labelMap = [
            'work'     => 'werk',
            'home'     => 'thuis',
            'personal' => 'eigen',
            'private'  => 'eigen',
            'mobile'   => 'mobiel',
            'cell'     => 'mobiel',
        ];

        return $labelMap[$label] ?? $label;
    }
}
