<?php

namespace Tests\Unit;

use App\Enums\ContactLabel;
use App\Validators\ContactArrayValidator;
use PHPUnit\Framework\TestCase;

class ContactArrayValidatorTest extends TestCase
{
    public function test_passes_with_valid_email_array()
    {
        $validator = new ContactArrayValidator('email');

        $validEmails = [
            ['value' => 'test@example.com', 'label' => ContactLabel::Eigen->value, 'is_default' => true],
            ['value' => 'test2@example.com', 'label' => ContactLabel::Relatie->value, 'is_default' => false],
        ];

        $this->assertTrue($validator->passes('emails', $validEmails));
    }

    public function test_passes_with_valid_phone_array()
    {
        $validator = new ContactArrayValidator('telefoon');

        $validPhones = [
            ['value' => '+31612345678', 'label' => ContactLabel::Eigen->value, 'is_default' => true],
            ['value' => '+31687654321', 'label' => ContactLabel::Relatie->value, 'is_default' => false],
        ];

        $this->assertTrue($validator->passes('phones', $validPhones));
    }

    public function test_fails_when_not_array()
    {
        $validator = new ContactArrayValidator('email');

        $this->assertFalse($validator->passes('emails', 'not an array'));
        $this->assertStringContainsString('moet een array zijn', $validator->message());
    }

    public function test_fails_when_value_provided_without_label()
    {
        $validator = new ContactArrayValidator('email');

        $invalidEmails = [
            ['value' => 'test@example.com', 'is_default' => true], // Missing label
        ];

        $this->assertFalse($validator->passes('emails', $invalidEmails));
        $this->assertStringContainsString('label verplicht', $validator->message());
    }

    public function test_fails_with_invalid_label()
    {
        $validator = new ContactArrayValidator('email');

        $invalidEmails = [
            ['value' => 'test@example.com', 'label' => 'invalid_label', 'is_default' => true],
        ];

        $this->assertFalse($validator->passes('emails', $invalidEmails));
        $this->assertStringContainsString('eigen, relatie, anders', $validator->message());
    }

    public function test_phone_validator_allows_mobile_label()
    {
        $validator = new ContactArrayValidator('telefoon');

        $validPhones = [
            ['value' => '+31612345678', 'label' => ContactLabel::Relatie->value, 'is_default' => true],
        ];

        $this->assertTrue($validator->passes('phones', $validPhones));
    }

    public function test_fails_when_is_default_not_boolean()
    {
        $validator = new ContactArrayValidator('email');

        // Use an array as is_default value - this should definitely fail
        $invalidEmails = [
            ['value' => 'test@example.com', 'label' => ContactLabel::Eigen->value, 'is_default' => ['not_a_boolean']],
        ];

        $this->assertFalse($validator->passes('emails', $invalidEmails));
        $this->assertStringContainsString('boolean waarde zijn', $validator->message());
    }

    public function test_fails_when_multiple_items_without_default()
    {
        $validator = new ContactArrayValidator('email');

        $emailsWithoutDefault = [
            ['value' => 'test1@example.com', 'label' => ContactLabel::Eigen->value, 'is_default' => false],
            ['value' => 'test2@example.com', 'label' => ContactLabel::Relatie->value, 'is_default' => false],
        ];

        $this->assertFalse($validator->passes('emails', $emailsWithoutDefault));
        $this->assertStringContainsString('standaard zijn gemarkeerd', $validator->message());
    }

    public function test_passes_with_empty_values()
    {
        $validator = new ContactArrayValidator('email');

        $emptyEmails = [
            ['value' => '', 'label' => ContactLabel::Eigen->value, 'is_default' => true],
        ];

        $this->assertTrue($validator->passes('emails', $emptyEmails));
    }

    public function test_passes_single_item_without_explicit_default()
    {
        $validator = new ContactArrayValidator('email');

        $singleEmail = [
            ['value' => 'test@example.com', 'label' => ContactLabel::Eigen->value],
        ];

        $this->assertTrue($validator->passes('emails', $singleEmail));
    }

    public function test_converts_string_to_boolean_for_is_default()
    {
        $validator = new ContactArrayValidator('email');

        // Test various string representations that should be converted to boolean
        $emailsWithStringDefaults = [
            ['value' => 'test1@example.com', 'label' => ContactLabel::Eigen->value, 'is_default' => 'true'],
            ['value' => 'test2@example.com', 'label' => ContactLabel::Relatie->value, 'is_default' => '1'],
            ['value' => 'test3@example.com', 'label' => ContactLabel::Anders->value, 'is_default' => 'on'],
        ];

        // This should pass because strings are converted to booleans
        $this->assertTrue($validator->passes('emails', $emailsWithStringDefaults));
    }

    public function test_converts_falsy_strings_to_false()
    {
        $validator = new ContactArrayValidator('email');

        // Test strings that should be converted to false
        $emailsWithFalsyDefaults = [
            ['value' => 'test1@example.com', 'label' => ContactLabel::Eigen->value, 'is_default' => 'false'],
            ['value' => 'test2@example.com', 'label' => ContactLabel::Relatie->value, 'is_default' => '0'],
            ['value' => 'test3@example.com', 'label' => ContactLabel::Anders->value, 'is_default' => 'random_string'],
        ];

        // This should fail because no item has is_default = true
        $this->assertFalse($validator->passes('emails', $emailsWithFalsyDefaults));
        $this->assertStringContainsString('standaard zijn gemarkeerd', $validator->message());
    }
}
