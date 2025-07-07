<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\WithFaker;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication, WithFaker;

    // RefreshDatabase
    protected function setUp(): void
    {
        parent::setUp();

        // Use testing database for all tests
        config(['database.default' => 'mysql_testing']);
        $this->seed();
    }
}
