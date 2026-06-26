<?php

namespace App\Console\Commands;

use App\Services\Mail\MailboxConfig;
use App\Services\Mail\MicrosoftGraphTokenService;
use Exception;
use Illuminate\Console\Command as IlluminateCommand;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CleanupGraphInbox extends IlluminateCommand
{
    protected $signature = 'emails:cleanup-graph-inbox
                            {--days= : Number of days to keep (default from config)}
                            {--mailbox= : Only clean the mailbox with this key (e.g. privatescan or herniapoli)}';

    protected $description = 'Delete emails older than specified days from Microsoft Graph inboxes';

    public function __construct(private readonly MicrosoftGraphTokenService $tokenService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $days = $this->option('days') ?: config('mail.graph_inbox_retention_days', 14);

        if (! is_numeric($days) || $days < 0) {
            $this->error('Days must be a positive number.');

            return IlluminateCommand::FAILURE;
        }

        $cutoff = now()->subDays($days)->toIso8601String();
        $filterKey = $this->option('mailbox');
        $mailboxes = MailboxConfig::all();

        if (empty($mailboxes)) {
            $this->error('No mailboxes configured in config/mail.php under mail.mailboxes');

            return IlluminateCommand::FAILURE;
        }

        $this->info("Deleting Graph inbox emails older than {$days} days (before {$cutoff})...");

        $totalDeleted = 0;
        $totalErrors = 0;

        foreach ($mailboxes as $key => $mailboxConfig) {
            if ($filterKey && $filterKey !== $key) {
                continue;
            }

            $address = $mailboxConfig['address'] ?? null;

            if (empty($address)) {
                $this->warn("Mailbox '{$key}' has no address configured, skipping.");

                continue;
            }

            $this->info("Cleaning mailbox [{$key}] {$address} ...");

            try {
                [$deleted, $errors] = $this->cleanupMailbox($key, $address, $cutoff);
                $totalDeleted += $deleted;
                $totalErrors += $errors;
            } catch (Exception $e) {
                $this->error("  -> Failed: {$e->getMessage()}");
                $totalErrors++;
            }
        }

        $this->info("Deleted {$totalDeleted} messages from Graph inboxes.");

        if ($totalErrors > 0) {
            $this->warn("{$totalErrors} messages could not be deleted (see logs).");
        }

        Log::info('Graph inbox cleanup completed', [
            'deleted_count'  => $totalDeleted,
            'error_count'    => $totalErrors,
            'retention_days' => $days,
            'cutoff'         => $cutoff,
        ]);

        return IlluminateCommand::SUCCESS;
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function cleanupMailbox(string $mailboxKey, string $address, string $cutoff): array
    {
        $accessToken = $this->tokenService->getAccessToken($mailboxKey);
        $baseUrl = 'https://graph.microsoft.com/v1.0';
        $url = "{$baseUrl}/users/{$address}/mailFolders('Inbox')/messages";

        $deletedCount = 0;
        $errorCount = 0;

        do {
            $response = Http::withToken($accessToken)->get($url, [
                '$filter' => "receivedDateTime lt {$cutoff}",
                '$select' => 'id,receivedDateTime,subject',
                '$top'    => 50,
            ]);

            if (! $response->successful()) {
                throw new Exception('Failed to fetch messages: '.$response->body());
            }

            $messages = $response->json()['value'] ?? [];

            foreach ($messages as $message) {
                try {
                    $deleteResponse = Http::withToken($accessToken)
                        ->delete("{$baseUrl}/users/{$address}/messages/{$message['id']}");

                    if ($deleteResponse->successful()) {
                        $deletedCount++;
                    } else {
                        $errorCount++;
                        Log::warning('Failed to delete Graph inbox message', [
                            'mailbox_key' => $mailboxKey,
                            'message_id'  => $message['id'],
                            'subject'     => $message['subject'] ?? '(no subject)',
                            'status'      => $deleteResponse->status(),
                        ]);
                    }
                } catch (Exception $e) {
                    $errorCount++;
                    Log::error('Exception deleting Graph inbox message', [
                        'mailbox_key' => $mailboxKey,
                        'message_id'  => $message['id'],
                        'error'       => $e->getMessage(),
                    ]);
                }
            }
        } while (count($messages) === 50);

        $this->info("  -> Deleted {$deletedCount} message(s).");

        return [$deletedCount, $errorCount];
    }
}
