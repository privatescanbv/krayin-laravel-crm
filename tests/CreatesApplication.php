<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;

trait CreatesApplication
{
    /**
     * Creates the application.
     *
     * @return \Illuminate\Foundation\Application
     */
    public function createApplication()
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        // Remove config cache so phpunit.xml DB_CONNECTION=sqlite is used.
        // Otherwise cached config (MySQL) overrides test env vars.
        $configCache = dirname(__DIR__).'/bootstrap/cache/config.php';
        if (file_exists($configCache)) {
            unlink($configCache);
        }

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }
}
