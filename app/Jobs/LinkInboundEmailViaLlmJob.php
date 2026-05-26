<?php

namespace App\Jobs;

use App\Services\Mail\EmailLlmLinkingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;
use Webkul\Email\Models\Email;

class LinkInboundEmailViaLlmJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    /** @var list<int> */
    public array $backoff = [60];

    public int $timeout = 180;

    public function __construct(
        private readonly int $emailId,
    ) {}

    public function handle(EmailLlmLinkingService $emailLlmLinkingService): void
    {
        $email = Email::find($this->emailId);

        if (! $email || $email->has_relationships) {
            return;
        }

        $result = $emailLlmLinkingService->extractAndLink(
            email: $email,
            applyLinks: false
        );

        if ($result['status'] === 'error') {
            Log::warning('LLM email sender extraction failed', [
                'email_id' => $this->emailId,
                'error'    => $result['error'],
            ]);

            return;
        }

        if (in_array($result['status'], ['linked', 'matched'], true)) {
            Log::info('Linked inbound email via LLM sender extraction', [
                'email_id' => $this->emailId,
                'status'   => $result['status'],
                'links'    => $result['links'],
                'senders'  => $result['senders'],
            ]);

            return;
        }

        Log::info('LLM email sender extraction completed without CRM match', [
            'email_id' => $this->emailId,
            'status'   => $result['status'],
            'senders'  => $result['senders'],
        ]);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('LLM email linking job permanently failed', [
            'email_id' => $this->emailId,
            'error'    => $exception->getMessage(),
        ]);
    }
}
