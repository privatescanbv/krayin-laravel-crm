<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication, RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();

        // Use testing database connection for all tests
        Config::set('database.default', 'mysql_testing');

        // Clear cache to prevent state pollution between tests
        Cache::flush();

        // Set locale to English for consistent test messages
        app()->setLocale('en');
    }
}
