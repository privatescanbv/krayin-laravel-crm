<?php

namespace Tests\Unit;

use App\Support\EmailNormalizer;
use Tests\TestCase;

class EmailNormalizerTest extends TestCase
{
    /** @test */
    public function normalize_lowercases_email(): void
    {
        $this->assertSame('test@example.com', EmailNormalizer::normalize('Test@Example.COM'));
    }

    /** @test */
    public function normalize_trims_whitespace(): void
    {
        $this->assertSame('foo@bar.nl', EmailNormalizer::normalize('  foo@bar.nl  '));
    }

    /** @test */
    public function normalize_trims_and_lowercases(): void
    {
        $this->assertSame('user@domain.org', EmailNormalizer::normalize('  USER@Domain.ORG  '));
    }

    /** @test */
    public function normalize_returns_null_for_empty_string(): void
    {
        $this->assertNull(EmailNormalizer::normalize(''));
    }

    /** @test */
    public function normalize_returns_null_for_whitespace_only(): void
    {
        $this->assertNull(EmailNormalizer::normalize('   '));
    }

    /** @test */
    public function normalize_preserves_already_lowercase_email(): void
    {
        $this->assertSame('already@lowercase.nl', EmailNormalizer::normalize('already@lowercase.nl'));
    }
}
