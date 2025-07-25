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

    public function passes($attribute, $value)
    {
        try {
            if (!is_array($value)) {
                $this->message = "Het {$this->type} veld moet een array zijn.";
                return false;
            }

            // Simple validation - just check if it's an array
            return true;
        } catch (\Exception $e) {
            $this->message = "Validation error: " . $e->getMessage();
            return false;
        }
    }

    public function message()
    {
        return $this->message ?: "Het {$this->type} veld heeft een ongeldige structuur.";
    }
}
