<?php

namespace Tests;

use Illuminate\Foundation\Testing\DatabaseMigrations as BaseDatabaseMigrations;

trait DatabaseMigrations
{
    use BaseDatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Run migrations instead of loading schema file
        $this->artisan('migrate:fresh');
    }
}