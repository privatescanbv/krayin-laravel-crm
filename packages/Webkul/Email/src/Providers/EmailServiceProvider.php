<?php

namespace Webkul\Email\Providers;

use Exception;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use App\Services\Mail\GraphMailService;
use App\Services\Mail\ImapEmailProcessor;
use Webkul\Email\Console\Commands\ProcessInboundEmails;
use Webkul\Email\InboundEmailProcessor\Contracts\InboundEmailProcessor;
use Webkul\Email\InboundEmailProcessor\SendgridEmailProcessor;
use Webkul\Email\InboundEmailProcessor\WebklexImapEmailProcessor;

class EmailServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        $this->app->bind(InboundEmailProcessor::class, function ($app) {
            $driver = config('mail-receiver.default');
            if (!$driver || !Str::contains($driver, 'webklex')) {
                logger()->warning('Binding InboundEmailProcessor with driver: '.$driver);
            }

            if ($driver === 'sendgrid') {
                return $app->make(SendgridEmailProcessor::class);
            }

            if ($driver === 'webklex-imap') {
                return $app->make(WebklexImapEmailProcessor::class);
            }

            if ($driver === 'imap') {
                return $app->make(ImapEmailProcessor::class);
            }

            if ($driver === 'microsoft-graph') {
                return $app->make(GraphMailService::class);
            }

            throw new Exception("Unsupported mail receiver driver [{$driver}].");
        });
    }

    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->registerCommands();
    }

    /**
     * Register the console commands of this package.
     */
    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                ProcessInboundEmails::class,
            ]);
        }
    }
}
