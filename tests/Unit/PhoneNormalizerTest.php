<?php

namespace Tests\Unit;

use App\Support\PhoneNormalizer;
use Tests\TestCase;

class PhoneNormalizerTest extends TestCase
{
    // --- toE164 ---

    /** @test */
    public function to_e164_converts_dutch_local_to_e164(): void
    {
        $this->assertSame('+31612345678', PhoneNormalizer::toE164('0612345678'));
    }

    /** @test */
    public function to_e164_keeps_existing_e164(): void
    {
        $this->assertSame('+31612345678', PhoneNormalizer::toE164('+31612345678'));
    }

    /** @test */
    public function to_e164_normalizes_with_spaces_and_dashes(): void
    {
        $this->assertSame('+31612345678', PhoneNormalizer::toE164('06 12-34 56 78'));
    }

    /** @test */
    public function to_e164_converts_31_prefix_without_plus(): void
    {
        $this->assertSame('+31612345678', PhoneNormalizer::toE164('31612345678'));
    }

    /** @test */
    public function to_e164_returns_null_for_empty_string(): void
    {
        $this->assertNull(PhoneNormalizer::toE164(''));
        $this->assertNull(PhoneNormalizer::toE164('   '));
    }

    /** @test */
    public function to_e164_strips_non_digit_chars_from_e164_input(): void
    {
        $this->assertSame('+31612345678', PhoneNormalizer::toE164('+31 6 12 34 56 78'));
    }

    // --- toDutchLocal ---

    /** @test */
    public function to_dutch_local_converts_e164_to_local(): void
    {
        $this->assertSame('0612345678', PhoneNormalizer::toDutchLocal('+31612345678'));
    }

    /** @test */
    public function to_dutch_local_keeps_already_local(): void
    {
        $this->assertSame('0612345678', PhoneNormalizer::toDutchLocal('0612345678'));
    }

    /** @test */
    public function to_dutch_local_converts_31_prefix_without_plus(): void
    {
        $this->assertSame('0612345678', PhoneNormalizer::toDutchLocal('31612345678'));
    }

    /** @test */
    public function to_dutch_local_strips_formatting(): void
    {
        $this->assertSame('0612345678', PhoneNormalizer::toDutchLocal('+31 6 12-34-56-78'));
    }
}
