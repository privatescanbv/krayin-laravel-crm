<?php

namespace Webkul\Contact\Traits;

trait HasPersonName
{
    /**
     * Build the full last name parts based on the Dutch naming convention.
     *
     * When a married name is present, the married name comes first (prefix after surname),
     * followed by the birth name (prefix after surname), separated by a dash:
     *  {married_name_prefix} {married_name} - {lastname_prefix}  {last_name}
     *
     * Without a married name, the traditional format is used:
     *   {lastname_prefix} {last_name}
     */
    public function getFullLastNameParts(): array
    {
        $parts = [];
        $rawLastName = $this->attributes['last_name'] ?? null;

        if (! empty($this->married_name)) {
            $marriedParts = [];
            if ($this->married_name_prefix) {
                $marriedParts[] = trim($this->married_name_prefix);
            }
            $marriedParts[] = trim($this->married_name);

            $marriedStr = implode(' ', array_filter($marriedParts));

            $birthParts = [];
            if ($this->lastname_prefix) {
                $birthParts[] = trim($this->lastname_prefix);
            }
            if ($rawLastName) {
                $birthParts[] = trim($rawLastName);
            }
            $birthStr = implode(' ', array_filter($birthParts));

            if ($marriedStr && $birthStr) {
                $parts[] = $marriedStr.' - '.$birthStr;
            } elseif ($marriedStr) {
                $parts[] = $marriedStr;
            } elseif ($birthStr) {
                $parts[] = $birthStr;
            }
        } else {
            if ($this->lastname_prefix) {
                $parts[] = trim($this->lastname_prefix);
            }
            if ($rawLastName) {
                $parts[] = trim($rawLastName);
            }
        }

        return $parts;
    }

    /**
     * Get the full last name (without first name).
     */
    public function getFullLastNameAttribute(): string
    {
        return implode(' ', array_filter($this->getFullLastNameParts()));
    }
}
