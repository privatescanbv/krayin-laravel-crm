<?php

namespace App\Validators;

use Illuminate\Contracts\Validation\Rule;

class ContactArrayValidator implements Rule
{
    protected $type;

    protected $message;

    public function __construct($type = 'contact')
    {
        $this->type = $type;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        if (! is_array($value)) {
            $this->message = "Het {$this->type} veld moet een array zijn.";

            return false;
        }

        // Create a normalized copy of the value for validation
        $normalizedValue = [];

        foreach ($value as $index => $item) {
            if (! is_array($item)) {
                $this->message = "Elk {$this->type} item moet een object zijn.";

                return false;
            }

            $normalizedItem = $item;

            // Check required fields - only validate if value is not empty
            // Allow empty values but require label to be present
            if (isset($item['value']) && ! empty(trim($item['value']))) {
                // If value is provided, label must also be provided
                if (! isset($item['label']) || empty(trim($item['label']))) {
                    $this->message = "Als een {$this->type} waarde wordt opgegeven, is het label verplicht.";

                    return false;
                }
            }

            // If label is provided, it must be valid
            if (isset($item['label']) && ! empty(trim($item['label']))) {
                // Validate label values using ContactLabel enum
                $validLabels = array_map(fn($c) => $c->value, \App\Enums\ContactLabel::cases());

                if (! in_array($item['label'], $validLabels, true)) {
                    $validLabelsStr = implode(', ', $validLabels);
                    $this->message = "Het {$this->type} label moet een van de volgende waarden zijn: {$validLabelsStr}.";

                    return false;
                }
            }

            // is_default is optional, but if present should be boolean or boolean-like
            if (isset($item['is_default'])) {
                // Convert string representations to boolean
                if (is_string($item['is_default'])) {
                    $normalizedItem['is_default'] = in_array(strtolower($item['is_default']), ['true', '1', 'on', 'yes']);
                } elseif (is_numeric($item['is_default'])) {
                    $normalizedItem['is_default'] = (bool) $item['is_default'];
                } elseif (! is_bool($item['is_default'])) {
                    $this->message = "Het {$this->type} veld 'is_default' moet een boolean waarde zijn.";

                    return false;
                } else {
                    $normalizedItem['is_default'] = $item['is_default'];
                }
            }

            $normalizedValue[] = $normalizedItem;
        }

        // Check that at least one non-empty item has is_default = true if multiple non-empty items
        $nonEmptyItems = array_filter($normalizedValue, function ($item) {
            return isset($item['value']) && ! empty(trim($item['value']));
        });

        if (count($nonEmptyItems) > 1) {
            $hasDefault = false;
            foreach ($nonEmptyItems as $item) {
                if (isset($item['is_default']) && $item['is_default'] === true) {
                    $hasDefault = true;
                    break;
                }
            }
            if (! $hasDefault) {
                $this->message = "Er moet ten minste één {$this->type} als standaard zijn gemarkeerd.";

                return false;
            }
        }

        return true;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return $this->message ?: "Het {$this->type} veld heeft een ongeldige structuur.";
    }
}
