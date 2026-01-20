<?php

namespace App\Traits;

use Exception;

/**
 * Trait for models with emails and phones arrays that need default value extraction.
 *
 * Requires the model to have 'emails' and 'phones' attributes cast to array.
 */
trait HasDefaultContactInfo
{
    public function findDefaultEmailOrError(): string
    {
        return $this->findDefaultEmail() ?? throw new Exception('No default email found for '.class_basename($this).' ID '.$this->id);
    }

    public function findDefaultEmail(): ?string
    {
        if (empty($this->emails)) {
            return null;
        }

        foreach ($this->emails as $email) {
            if (isset($email['is_default']) && ($email['is_default'] === true || $email['is_default'] === 'on' || $email['is_default'] === '1')) {
                return $email['value'] ?? null;
            }
        }

        return $this->emails[0]['value'] ?? null;
    }

    public function findDefaultPhone(): ?string
    {
        if (empty($this->phones)) {
            return null;
        }

        foreach ($this->phones as $phone) {
            if (isset($phone['is_default']) && ($phone['is_default'] === true || $phone['is_default'] === 'on' || $phone['is_default'] === '1')) {
                return $phone['value'] ?? null;
            }
        }

        return $this->phones[0]['value'] ?? null;
    }
}
