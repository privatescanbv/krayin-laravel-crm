<?php

// Simple class definition for testing
class ContactArrayValidator
{
    protected $type;
    protected $message;

    public function __construct($type = 'contact')
    {
        $this->type = $type;
    }

    public function passes($attribute, $value)
    {
        if (!is_array($value)) {
            $this->message = "Het {$this->type} veld moet een array zijn.";
            return false;
        }

        // Create a normalized copy of the value for validation
        $normalizedValue = [];
        
        foreach ($value as $index => $item) {
            if (!is_array($item)) {
                $this->message = "Elk {$this->type} item moet een object zijn.";
                return false;
            }

            $normalizedItem = $item;

            // is_default is optional, but if present should be boolean or boolean-like
            if (isset($item['is_default'])) {
                // Convert string representations to boolean
                if (is_string($item['is_default'])) {
                    $normalizedItem['is_default'] = in_array(strtolower($item['is_default']), ['true', '1', 'on', 'yes']);
                } elseif (is_numeric($item['is_default'])) {
                    $normalizedItem['is_default'] = (bool) $item['is_default'];
                } elseif (!is_bool($item['is_default'])) {
                    $this->message = "Het {$this->type} veld 'is_default' moet een boolean waarde zijn.";
                    return false;
                } else {
                    $normalizedItem['is_default'] = $item['is_default'];
                }
            }
            
            $normalizedValue[] = $normalizedItem;
        }

        return true;
    }

    public function message()
    {
        return $this->message ?: "Het {$this->type} veld heeft een ongeldige structuur.";
    }
}

echo "Testing ContactArrayValidator...\n\n";

// Test 1: Array as is_default should fail
echo "Test 1: Array as is_default should fail\n";
$validator = new ContactArrayValidator('email');
$invalidEmails = [
    ['value' => 'test@example.com', 'label' => 'work', 'is_default' => ['not_a_boolean']]
];
$result = $validator->passes('emails', $invalidEmails);
echo "Result: " . ($result ? 'PASS' : 'FAIL') . "\n";
echo "Message: " . $validator->message() . "\n\n";

// Test 2: String 'true' should pass and convert to boolean
echo "Test 2: String 'true' should pass\n";
$validator = new ContactArrayValidator('email');
$validEmails = [
    ['value' => 'test@example.com', 'label' => 'work', 'is_default' => 'true']
];
$result = $validator->passes('emails', $validEmails);
echo "Result: " . ($result ? 'PASS' : 'FAIL') . "\n";
echo "Message: " . $validator->message() . "\n\n";

// Test 3: String 'not_boolean' should convert to false
echo "Test 3: String 'not_boolean' should convert to false\n";
$validator = new ContactArrayValidator('email');
$emails = [
    ['value' => 'test@example.com', 'label' => 'work', 'is_default' => 'not_boolean']
];
$result = $validator->passes('emails', $emails);
echo "Result: " . ($result ? 'PASS' : 'FAIL') . "\n";
echo "Message: " . $validator->message() . "\n\n";

echo "Testing complete.\n";