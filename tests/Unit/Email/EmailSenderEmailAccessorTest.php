<?php

namespace Tests\Unit\Email;

use PHPUnit\Framework\TestCase;
use Webkul\Email\Models\Email;

class EmailSenderEmailAccessorTest extends TestCase
{
    /**
     * Test the new standardized format: {"name": "...", "email": "..."}
     */
    public function test_standardized_format_with_name_and_email()
    {
        $email = new Email;
        $email->from = ['name' => 'Test User', 'email' => 'test@example.com'];
        $this->assertSame('test@example.com', $email->sender_email);
    }

    public function test_standardized_format_with_empty_name()
    {
        $email = new Email;
        $email->from = ['name' => '', 'email' => 'test@example.com'];
        $this->assertSame('test@example.com', $email->sender_email);
    }

    public function test_standardized_format_as_json_string()
    {
        $email = new Email;
        $email->from = '{"name":"Test User","email":"test@example.com"}';
        $this->assertSame('test@example.com', $email->sender_email);
    }

    public function test_standardized_format_as_json_string_with_empty_name()
    {
        $email = new Email;
        $email->from = '{"name":"","email":"test@example.com"}';
        $this->assertSame('test@example.com', $email->sender_email);
    }

    /**
     * Backward compatibility tests for legacy formats
     */
    public function test_plain_string_from_is_returned()
    {
        $email = new Email;
        $email->from = 'test@example.com';
        $this->assertSame('test@example.com', $email->sender_email);
    }

    public function test_array_of_strings_from_is_returned()
    {
        $email = new Email;
        $email->from = ['test@example.com'];
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

    public function test_json_string_array_is_decoded_and_returned()
    {
        $email = new Email;
        $email->from = '["test@example.com"]';
        $this->assertSame('test@example.com', $email->sender_email);
    }
}
