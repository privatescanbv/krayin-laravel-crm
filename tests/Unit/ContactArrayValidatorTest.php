<?php

namespace Tests\Unit;

use App\Validators\ContactArrayValidator;
use PHPUnit\Framework\TestCase;

class ContactArrayValidatorTest extends TestCase
{
    public function test_passes_with_valid_email_array()
    {
        $validator = new ContactArrayValidator('email');
        
        $validEmails = [
            ['value' => 'test@example.com', 'label' => 'work', 'is_default' => true],
            ['value' => 'test2@example.com', 'label' => 'home', 'is_default' => false]
        ];
        
        $this->assertTrue($validator->passes('emails', $validEmails));
    }

    public function test_passes_with_valid_phone_array()
    {
        $validator = new ContactArrayValidator('telefoon');
        
        $validPhones = [
            ['value' => '0612345678', 'label' => 'work', 'is_default' => true],
            ['value' => '0687654321', 'label' => 'mobile', 'is_default' => false]
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
            ['value' => 'test@example.com', 'is_default' => true] // Missing label
        ];
        
        $this->assertFalse($validator->passes('emails', $invalidEmails));
        $this->assertStringContainsString('label verplicht', $validator->message());
    }

    public function test_fails_with_invalid_label()
    {
        $validator = new ContactArrayValidator('email');
        
        $invalidEmails = [
            ['value' => 'test@example.com', 'label' => 'invalid_label', 'is_default' => true]
        ];
        
        $this->assertFalse($validator->passes('emails', $invalidEmails));
        $this->assertStringContainsString('work, home, other', $validator->message());
    }

    public function test_phone_validator_allows_mobile_label()
    {
        $validator = new ContactArrayValidator('telefoon');
        
        $validPhones = [
            ['value' => '0612345678', 'label' => 'mobile', 'is_default' => true]
        ];
        
        $this->assertTrue($validator->passes('phones', $validPhones));
    }

    public function test_fails_when_is_default_not_boolean()
    {
        $validator = new ContactArrayValidator('email');
        
        $invalidEmails = [
            ['value' => 'test@example.com', 'label' => 'work', 'is_default' => 'not_boolean']
        ];
        
        $this->assertFalse($validator->passes('emails', $invalidEmails));
        $this->assertStringContainsString('boolean zijn', $validator->message());
    }

    public function test_fails_when_multiple_items_without_default()
    {
        $validator = new ContactArrayValidator('email');
        
        $emailsWithoutDefault = [
            ['value' => 'test1@example.com', 'label' => 'work', 'is_default' => false],
            ['value' => 'test2@example.com', 'label' => 'home', 'is_default' => false]
        ];
        
        $this->assertFalse($validator->passes('emails', $emailsWithoutDefault));
        $this->assertStringContainsString('standaard zijn gemarkeerd', $validator->message());
    }

    public function test_passes_with_empty_values()
    {
        $validator = new ContactArrayValidator('email');
        
        $emptyEmails = [
            ['value' => '', 'label' => 'work', 'is_default' => true]
        ];
        
        $this->assertTrue($validator->passes('emails', $emptyEmails));
    }

    public function test_passes_single_item_without_explicit_default()
    {
        $validator = new ContactArrayValidator('email');
        
        $singleEmail = [
            ['value' => 'test@example.com', 'label' => 'work']
        ];
        
        $this->assertTrue($validator->passes('emails', $singleEmail));
    }
}