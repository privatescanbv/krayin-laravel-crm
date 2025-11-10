<?php

namespace Tests\Unit\Email;

use PHPUnit\Framework\TestCase;
use Webkul\Email\Models\Email;

class EmailSenderEmailAccessorTest extends TestCase
{
    public function test_plain_string_from_is_returned()
    {
        $email = new Email;
        $email->from = 'test@example.com';
        $this->assertSame('test@example.com', $email->sender_email);
    }

    public function test_array_of_strings_from_is_returned()
    {
        $email = new Email;
        $email->from = '[test@example.com]';
        $this->assertSame('test@example.com', $email->sender_email);
    }

    public function test_array_of_objects_value_key_is_returned()
    {
        $email = new Email;
        $email->from = [['value' => 'test@example.com', 'label' => 'eigen']];
        $this->assertSame('test@example.com', $email->sender_email);
    }

    public function test_array_of_objects_email_key_is_returned()
    {
        $email = new Email;
        $email->from = [['email' => 'test@example.com', 'name' => 'Tester']];
        $this->assertSame('test@example.com', $email->sender_email);
    }

    public function test_json_string_array_inside_array_is_unwrapped()
    {
        $email = new Email;
        $email->from = ['["test@example.com"]'];
        $this->assertSame('test@example.com', $email->sender_email);
    }

    public function test_json_string_array_is_decoded_and_returned()
    {
        $email = new Email;
        $email->from = '["test@example.com"]';
        $this->assertSame('test@example.com', $email->sender_email);
    }

    public function test_value_key_with_json_array_is_unwrapped()
    {
        $email = new Email;
        $email->from = [['value' => '["test@example.com"]']];
        $this->assertSame('test@example.com', $email->sender_email);
    }

    public function test_email_key_with_json_array_is_unwrapped()
    {
        $email = new Email;
        $email->from = [['email' => '["test@example.com"]']];
        $this->assertSame('test@example.com', $email->sender_email);
    }

    public function test_single_object_value_with_json_array_is_unwrapped()
    {
        $email = new Email;
        $email->from = ['value' => '["test@example.com"]'];
        $this->assertSame('test@example.com', $email->sender_email);
    }

    public function test_single_object_email_with_json_array_is_unwrapped()
    {
        $email = new Email;
        $email->from = ['email' => '["test@example.com"]'];
        $this->assertSame('test@example.com', $email->sender_email);
    }
}
