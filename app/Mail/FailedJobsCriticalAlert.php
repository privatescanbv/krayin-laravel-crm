<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class FailedJobsCriticalAlert extends Mailable
{
    public readonly string $appName;

    public readonly string $environment;

    public readonly string $timestamp;

    public function __construct(public readonly int $failedJobCount)
    {
        $this->appName = config('app.name');
        $this->environment = app()->environment();
        $this->timestamp = now()->format('d-m-Y H:i:s');
    }

    public function build(): self
    {
        return $this
            ->subject("[{$this->appName} - {$this->environment}] KRITIEK – {$this->failedJobCount} mislukte jobs in de queue")
            ->view('emails.failed-jobs.critical');
    }
}
