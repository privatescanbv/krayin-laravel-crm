<?php

namespace Tests;

use Database\Seeders\DepartmentSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Webkul\Installer\Database\Seeders\Attribute\AttributeSeeder;
use Webkul\Installer\Database\Seeders\Lead\PipelineSeeder;
use Webkul\Installer\Database\Seeders\Lead\SourceSeeder;
use Webkul\Installer\Database\Seeders\Lead\TypeSeeder;

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
        $this->artisan('db:seed', ['--class' => DepartmentSeeder::class]);
        $this->artisan('db:seed', ['--class' => TypeSeeder::class]);
        $this->artisan('db:seed', ['--class' => SourceSeeder::class]);
    }
}
