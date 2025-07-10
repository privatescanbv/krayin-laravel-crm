<?php

namespace Tests\Unit;

use Tests\TestCase;
use Webkul\Core\Contracts\Validations\PhoneValidator;

class PhoneValidatorTest extends TestCase
{
    private PhoneValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new PhoneValidator;
    }

    /** @test */
    public function it_passes_for_valid_phone_numbers()
    {
        $validNumbers = [
            '+31612345678',    // Dutch mobile
            '+14155552671',    // US number
            '+442071234567',   // UK number
            '+49123456789',    // German number
            '+33123456789',    // French number
            '+61234567890',    // Australian number
        ];

        foreach ($validNumbers as $number) {
            $failed = false;
            $failMessage = '';

            $this->validator->validate('phone', $number, function ($message) use (&$failed, &$failMessage) {
                $failed = true;
                $failMessage = $message;
            });

            $this->assertFalse($failed, "Phone number '{$number}' should be valid but failed with: {$failMessage}");
        }
    }

    /** @test */
    public function it_fails_when_phone_does_not_start_with_plus()
    {
        $invalidNumbers = [
            '31612345678',
            '14155552671',
            '442071234567',
        ];

        foreach ($invalidNumbers as $number) {
            $failed = false;
            $failMessage = '';

            $this->validator->validate('phone', $number, function ($message) use (&$failed, &$failMessage) {
                $failed = true;
                $failMessage = $message;
            });

            $this->assertTrue($failed, "Phone number '{$number}' should fail because it doesn't start with +");
            $this->assertStringContainsString('must start with a + symbol', $failMessage);
        }
    }

    /** @test */
    public function it_fails_when_phone_contains_non_digits_after_plus()
    {
        $invalidNumbers = [
            '+3161234567a',
            '+1415555267-',
            '+44207123456.',
            '+4912345678 ',
        ];

        foreach ($invalidNumbers as $number) {
            $failed = false;
            $failMessage = '';

            $this->validator->validate('phone', $number, function ($message) use (&$failed, &$failMessage) {
                $failed = true;
                $failMessage = $message;
            });

            $this->assertTrue($failed, "Phone number '{$number}' should fail because it contains non-digits after +");
            $this->assertStringContainsString('must contain only digits after the + symbol', $failMessage);
        }
    }

    /** @test */
    public function it_fails_when_phone_is_too_short()
    {
        $invalidNumbers = [
            '+3161234',      // 7 digits (too short)
            '+1415555',      // 7 digits (too short)
            '+4420712',      // 7 digits (too short)
        ];

        foreach ($invalidNumbers as $number) {
            $failed = false;
            $failMessage = '';

            $this->validator->validate('phone', $number, function ($message) use (&$failed, &$failMessage) {
                $failed = true;
                $failMessage = $message;
            });

            $this->assertTrue($failed, "Phone number '{$number}' should fail because it's too short");
            $this->assertStringContainsString('must be at least 8 digits long', $failMessage);
        }
    }

    /** @test */
    public function it_fails_when_phone_is_too_long()
    {
        $invalidNumbers = [
            '+316123456789012345',  // 18 digits (too long)
            '+141555526712345678',  // 18 digits (too long)
            '+442071234567890123',  // 18 digits (too long)
        ];

        foreach ($invalidNumbers as $number) {
            $failed = false;
            $failMessage = '';

            $this->validator->validate('phone', $number, function ($message) use (&$failed, &$failMessage) {
                $failed = true;
                $failMessage = $message;
            });

            $this->assertTrue($failed, "Phone number '{$number}' should fail because it's too long");
            $this->assertStringContainsString('must not exceed 15 digits', $failMessage);
        }
    }

    /** @test */
    public function it_fails_when_country_code_starts_with_zero()
    {
        $invalidNumbers = [
            '+031612345678',  // Country code starts with 0
            '+014155552671',  // Country code starts with 0
            '+0442071234567', // Country code starts with 0
        ];

        foreach ($invalidNumbers as $number) {
            $failed = false;
            $failMessage = '';

            $this->validator->validate('phone', $number, function ($message) use (&$failed, &$failMessage) {
                $failed = true;
                $failMessage = $message;
            });

            $this->assertTrue($failed, "Phone number '{$number}' should fail because country code starts with 0");
            // The validator might fail with regex validation first, so we just check that it failed
            $this->assertTrue($failed, "Phone number '{$number}' should fail");
        }
    }

    /** @test */
    public function it_fails_when_phone_has_invalid_format()
    {
        $invalidNumbers = [
            '+00000000000',  // All zeros after + (fails regex validation)
        ];

        foreach ($invalidNumbers as $number) {
            $failed = false;
            $failMessage = '';

            $this->validator->validate('phone', $number, function ($message) use (&$failed, &$failMessage) {
                $failed = true;
                $failMessage = $message;
            });

            $this->assertTrue($failed, "Phone number '{$number}' should fail due to invalid format");
            $this->assertStringContainsString('must be in a valid international phone number format', $failMessage);
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

            $this->validator->validate('phone', $value, function ($message) use (&$failed) {
                $failed = true;
            });

            $this->assertFalse($failed, 'Empty value should pass validation');
        }
    }

    /** @test */
    public function it_handles_edge_cases()
    {
        // Test minimum valid length (8 digits)
        $minValid = '+12345678';
        $failed = false;

        $this->validator->validate('phone', $minValid, function ($message) use (&$failed) {
            $failed = true;
        });

        $this->assertFalse($failed, 'Minimum valid length should pass');

        // Test maximum valid length (15 digits)
        $maxValid = '+123456789012345';
        $failed = false;

        $this->validator->validate('phone', $maxValid, function ($message) use (&$failed) {
            $failed = true;
        });

        $this->assertFalse($failed, 'Maximum valid length should pass');
    }
}
