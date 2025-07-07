<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Webkul\Installer\Database\Seeders\Attribute\AttributeSeeder;
use Webkul\Installer\Database\Seeders\Lead\PipelineSeeder;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication, WithFaker;

    // RefreshDatabase
    protected function setUp(): void
    {
        parent::setUp();

        // Use testing database for all tests
        config(['database.default' => 'mysql_testing']);
        $this->artisan('db:seed', ['--class' => PipelineSeeder::class]);
        $this->artisan('db:seed', ['--class' => AttributeSeeder::class]);
    }
}
