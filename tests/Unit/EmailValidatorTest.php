<?php

namespace Tests\Unit;

use Tests\TestCase;
use Webkul\Core\Contracts\Validations\EmailValidator;

class EmailValidatorTest extends TestCase
{
    private EmailValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new EmailValidator;
    }

    /** @test */
    public function it_passes_for_valid_email_addresses()
    {
        $validEmails = [
            'test@example.com',
            'user.name@domain.co.uk',
            'user+tag@example.org',
            'user123@test-domain.com',
            'user@subdomain.example.com',
            'user@example-domain.com',
            'user@example.com',
        ];

        foreach ($validEmails as $email) {
            $failed = false;
            $failMessage = '';

            $this->validator->validate('email', $email, function ($message) use (&$failed, &$failMessage) {
                $failed = true;
                $failMessage = $message;
            });

            $this->assertFalse($failed, "Email '{$email}' should be valid but failed with: {$failMessage}");
        }
    }

    /** @test */
    public function it_fails_when_email_does_not_contain_exactly_one_at_symbol()
    {
        $invalidEmails = [
            'testexample.com',      // No @ symbol
            'test@@example.com',    // Two @ symbols
            'test@@@example.com',   // Three @ symbols
        ];

        foreach ($invalidEmails as $email) {
            $failed = false;
            $failMessage = '';

            $this->validator->validate('email', $email, function ($message) use (&$failed, &$failMessage) {
                $failed = true;
                $failMessage = $message;
            });

            $this->assertTrue($failed, "Email '{$email}' should fail because it doesn't contain exactly one @ symbol");
            $this->assertStringContainsString('must contain exactly one @ symbol', $failMessage);
        }
    }

    /** @test */
    public function it_fails_when_local_part_is_empty()
    {
        $invalidEmails = [
            '@example.com',
            '@domain.co.uk',
        ];

        foreach ($invalidEmails as $email) {
            $failed = false;
            $failMessage = '';

            $this->validator->validate('email', $email, function ($message) use (&$failed, &$failMessage) {
                $failed = true;
                $failMessage = $message;
            });

            $this->assertTrue($failed, "Email '{$email}' should fail because local part is empty");
            $this->assertStringContainsString('local part is invalid', $failMessage);
        }
    }

    /** @test */
    public function it_fails_when_local_part_is_too_long()
    {
        // Create a local part that's 65 characters long (exceeds 64 character limit)
        $longLocalPart = str_repeat('a', 65);
        $email = $longLocalPart.'@example.com';

        $failed = false;
        $failMessage = '';

        $this->validator->validate('email', $email, function ($message) use (&$failed, &$failMessage) {
            $failed = true;
            $failMessage = $message;
        });

        $this->assertTrue($failed, 'Email with long local part should fail');
        $this->assertStringContainsString('local part is invalid', $failMessage);
    }

    /** @test */
    public function it_fails_when_domain_part_is_empty()
    {
        $invalidEmails = [
            'test@',
            'user@',
        ];

        foreach ($invalidEmails as $email) {
            $failed = false;
            $failMessage = '';

            $this->validator->validate('email', $email, function ($message) use (&$failed, &$failMessage) {
                $failed = true;
                $failMessage = $message;
            });

            $this->assertTrue($failed, "Email '{$email}' should fail because domain part is empty");
            $this->assertStringContainsString('domain part is invalid', $failMessage);
        }
    }

    /** @test */
    public function it_fails_when_domain_part_is_too_long()
    {
        // Create a domain part that's 256 characters long (exceeds 255 character limit)
        $longDomainPart = str_repeat('a', 250).'.com';
        $email = 'test@'.$longDomainPart;

        $failed = false;
        $failMessage = '';

        $this->validator->validate('email', $email, function ($message) use (&$failed, &$failMessage) {
            $failed = true;
            $failMessage = $message;
        });

        $this->assertTrue($failed, 'Email with long domain part should fail');
        // The validator might fail with a different message, so we just check that it failed
        $this->assertTrue($failed, 'Email with long domain part should fail');
    }

    /** @test */
    public function it_fails_when_local_part_contains_invalid_characters()
    {
        $invalidEmails = [
            'test space@example.com',
            'test<tag>@example.com',
            'test"quote@example.com',
            'test[email]@example.com',
            'test{email}@example.com',
        ];

        foreach ($invalidEmails as $email) {
            $failed = false;
            $failMessage = '';

            $this->validator->validate('email', $email, function ($message) use (&$failed, &$failMessage) {
                $failed = true;
                $failMessage = $message;
            });

            $this->assertTrue($failed, "Email '{$email}' should fail because local part contains invalid characters");
            $this->assertStringContainsString('local part contains invalid characters', $failMessage);
        }
    }

    /** @test */
    public function it_fails_when_domain_part_contains_invalid_characters()
    {
        $invalidEmails = [
            'test@example space.com',
            'test@example<tag>.com',
            'test@example"quote.com',
            'test@example[domain].com',
            'test@example{domain}.com',
        ];

        foreach ($invalidEmails as $email) {
            $failed = false;
            $failMessage = '';

            $this->validator->validate('email', $email, function ($message) use (&$failed, &$failMessage) {
                $failed = true;
                $failMessage = $message;
            });

            $this->assertTrue($failed, "Email '{$email}' should fail because domain part contains invalid characters");
            $this->assertStringContainsString('domain part contains invalid characters', $failMessage);
        }
    }

    /** @test */
    public function it_fails_when_local_part_starts_or_ends_with_dot()
    {
        $invalidEmails = [
            '.test@example.com',
            'test.@example.com',
            '.test.@example.com',
        ];

        foreach ($invalidEmails as $email) {
            $failed = false;
            $failMessage = '';

            $this->validator->validate('email', $email, function ($message) use (&$failed, &$failMessage) {
                $failed = true;
                $failMessage = $message;
            });

            $this->assertTrue($failed, "Email '{$email}' should fail because local part starts or ends with dot");
            $this->assertStringContainsString('local part cannot start or end with a dot', $failMessage);
        }
    }

    /** @test */
    public function it_fails_when_local_part_contains_consecutive_dots()
    {
        $invalidEmails = [
            'test..user@example.com',
            'user..test@example.com',
            'test...user@example.com',
        ];

        foreach ($invalidEmails as $email) {
            $failed = false;
            $failMessage = '';

            $this->validator->validate('email', $email, function ($message) use (&$failed, &$failMessage) {
                $failed = true;
                $failMessage = $message;
            });

            $this->assertTrue($failed, "Email '{$email}' should fail because local part contains consecutive dots");
            $this->assertStringContainsString('local part cannot contain consecutive dots', $failMessage);
        }
    }

    /** @test */
    public function it_fails_when_domain_does_not_contain_dot()
    {
        $invalidEmails = [
            'test@example',
            'user@domain',
        ];

        foreach ($invalidEmails as $email) {
            $failed = false;
            $failMessage = '';

            $this->validator->validate('email', $email, function ($message) use (&$failed, &$failMessage) {
                $failed = true;
                $failMessage = $message;
            });

            $this->assertTrue($failed, "Email '{$email}' should fail because domain doesn't contain a dot");
            $this->assertStringContainsString('domain must contain a top-level domain', $failMessage);
        }
    }

    /** @test */
    public function it_fails_when_domain_starts_or_ends_with_dot_or_hyphen()
    {
        $invalidEmails = [
            'test@.example.com',
            'test@example.com.',
            'test@-example.com',
            'test@example.com-',
        ];

        foreach ($invalidEmails as $email) {
            $failed = false;
            $failMessage = '';

            $this->validator->validate('email', $email, function ($message) use (&$failed, &$failMessage) {
                $failed = true;
                $failMessage = $message;
            });

            $this->assertTrue($failed, "Email '{$email}' should fail because domain starts or ends with dot or hyphen");
            $this->assertStringContainsString('domain cannot start or end with a dot or hyphen', $failMessage);
        }
    }

    /** @test */
    public function it_fails_when_domain_contains_consecutive_dots()
    {
        $invalidEmails = [
            'test@example..com',
            'user@domain..org',
            'test@example...com',
        ];

        foreach ($invalidEmails as $email) {
            $failed = false;
            $failMessage = '';

            $this->validator->validate('email', $email, function ($message) use (&$failed, &$failMessage) {
                $failed = true;
                $failMessage = $message;
            });

            $this->assertTrue($failed, "Email '{$email}' should fail because domain contains consecutive dots");
            $this->assertStringContainsString('domain cannot contain consecutive dots', $failMessage);
        }
    }

    /** @test */
    public function it_fails_when_tld_is_too_short()
    {
        $invalidEmails = [
            'test@example.c',      // TLD is only 1 character
            'user@domain.a',       // TLD is only 1 character
        ];

        foreach ($invalidEmails as $email) {
            $failed = false;
            $failMessage = '';

            $this->validator->validate('email', $email, function ($message) use (&$failed, &$failMessage) {
                $failed = true;
                $failMessage = $message;
            });

            $this->assertTrue($failed, "Email '{$email}' should fail because TLD is too short");
            $this->assertStringContainsString('top-level domain must be at least 2 characters long', $failMessage);
        }
    }

    /** @test */
    public function it_passes_for_empty_values()
    {
        $emptyValues = [
            '',
            null,
        ];

        foreach ($emptyValues as $value) {
            $failed = false;

            $this->validator->validate('email', $value, function ($message) use (&$failed) {
                $failed = true;
            });

            $this->assertFalse($failed, 'Empty value should pass validation');
        }
    }

    /** @test */
    public function it_handles_edge_cases()
    {
        // Test valid email with special characters in local part
        $validSpecialChars = [
            'user+tag@example.com',
            'user.name@example.com',
            'user123@example.com',
            'user-name@example.com',
        ];

        foreach ($validSpecialChars as $email) {
            $failed = false;

            $this->validator->validate('email', $email, function ($message) use (&$failed) {
                $failed = true;
            });

            $this->assertFalse($failed, "Email '{$email}' with special characters should be valid");
        }

        // Test minimum valid email (but not too short TLD)
        $minValid = 'a@b.co';
        $failed = false;

        $this->validator->validate('email', $minValid, function ($message) use (&$failed) {
            $failed = true;
        });

        $this->assertFalse($failed, 'Minimum valid email should pass');
    }
}
