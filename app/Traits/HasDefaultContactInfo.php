<?php

namespace App\Traits;

use App\Support\EmailNormalizer;
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

    /**
     * True when $address (case/whitespace-insensitive) is one of this model's own email
     * addresses. Used to verify an email's entity-link actually recognizes its address —
     * see App\Console\Commands\DetectMislinkedEmailEntities.
     */
    public function hasEmailAddress(string $address): bool
    {
        $normalized = EmailNormalizer::normalize($address);

        if ($normalized === null || empty($this->emails)) {
            return false;
        }

        foreach ($this->emails as $email) {
            if (EmailNormalizer::normalize((string) ($email['value'] ?? '')) === $normalized) {
                return true;
            }
        }

        return false;
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
