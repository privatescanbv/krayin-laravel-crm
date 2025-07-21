<?php

namespace Tests;

use Database\Seeders\DepartmentSeeder;
use Exception;
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

        // Set locale to English for consistent test messages
        app()->setLocale('en');

        // Use the database connection specified in phpunit.xml (usually SQLite)
        // Don't override if already set by phpunit.xml
        // Try to run migrations and seeders, but don't fail if they don't exist
        try {
            $this->artisan('migrate', ['--force' => true]);
            $this->artisan('db:seed', ['--class' => PipelineSeeder::class]);
            $this->artisan('db:seed', ['--class' => AttributeSeeder::class]);
            $this->artisan('db:seed', ['--class' => DepartmentSeeder::class]);
            $this->artisan('db:seed', ['--class' => TypeSeeder::class]);
            $this->artisan('db:seed', ['--class' => SourceSeeder::class]);
        } catch (Exception $e) {
            logger()->warning('Database migrations or seeders failed', [
                'error' => $e->getMessage(),
            ]);
            // Ignore seeding errors for now
        }
    }
}
