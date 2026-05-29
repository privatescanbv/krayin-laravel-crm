<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class FailedJobsWarningAlert extends Mailable
{
    public readonly string $environment;

    public readonly string $timestamp;

    public function __construct(public readonly int $failedJobCount)
    {
        $this->environment = app()->environment();
        $this->timestamp = now()->format('d-m-Y H:i:s');
    }

    public function build(): self
    {
        return $this
            ->subject("[{$this->environment}] Queue Warning – {$this->failedJobCount} mislukte jobs")
            ->view('emails.failed-jobs.warning');
    }
}
