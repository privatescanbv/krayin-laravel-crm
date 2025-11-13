<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\WithFaker;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication, RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();

        // Set locale to English for consistent test messages
        app()->setLocale('en');

        // Disable CSRF token verification for tests
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
    }
}
