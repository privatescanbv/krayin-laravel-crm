<?php

namespace App\Console\Commands;

use Exception;
use Illuminate\Console\Command as IlluminateCommand;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CleanupGraphInbox extends IlluminateCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'emails:cleanup-graph-inbox {--days= : Number of days to keep (default from config)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete emails older than specified days from the Microsoft Graph inbox';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $days = $this->option('days') ?: config('mail.graph_inbox_retention_days', 14);

        if (! is_numeric($days) || $days < 0) {
            $this->error('Days must be a positive number.');

            return IlluminateCommand::FAILURE;
        }

        $cutoff = now()->subDays($days)->toIso8601String();

        $this->info("Deleting Graph inbox emails older than {$days} days (before {$cutoff})...");

        try {
            $accessToken = $this->getAccessToken();
        } catch (Exception $e) {
            $this->error('Failed to get access token: '.$e->getMessage());

            return IlluminateCommand::FAILURE;
        }

        $baseUrl = 'https://graph.microsoft.com/v1.0';
        $mailbox = config('mail.graph.mailbox');
        $url = "{$baseUrl}/users/{$mailbox}/mailFolders('Inbox')/messages";

        $deletedCount = 0;
        $errorCount = 0;

        do {
            try {
                $response = Http::withToken($accessToken)->get($url, [
                    '$filter' => "receivedDateTime lt {$cutoff}",
                    '$select' => 'id,receivedDateTime,subject',
                    '$top'    => 50,
                ]);

                if (! $response->successful()) {
                    $this->error('Failed to fetch messages: '.$response->body());

                    return IlluminateCommand::FAILURE;
                }

                $messages = $response->json()['value'] ?? [];
            } catch (Exception $e) {
                $this->error('Error fetching messages: '.$e->getMessage());

                return IlluminateCommand::FAILURE;
            }

            foreach ($messages as $message) {
                try {
                    $deleteResponse = Http::withToken($accessToken)
                        ->delete("{$baseUrl}/users/{$mailbox}/messages/{$message['id']}");

                    if ($deleteResponse->successful()) {
                        $deletedCount++;
                    } else {
                        $errorCount++;
                        Log::warning('Failed to delete Graph inbox message', [
                            'message_id' => $message['id'],
                            'subject'    => $message['subject'] ?? '(no subject)',
                            'status'     => $deleteResponse->status(),
                        ]);
                    }
                } catch (Exception $e) {
                    $errorCount++;
                    Log::error('Exception deleting Graph inbox message', [
                        'message_id' => $message['id'],
                        'error'      => $e->getMessage(),
                    ]);
                }
            }

        } while (count($messages) === 50);

        $this->info("Deleted {$deletedCount} messages from Graph inbox.");

        if ($errorCount > 0) {
            $this->warn("{$errorCount} messages could not be deleted (see logs).");
        }

        Log::info('Graph inbox cleanup completed', [
            'deleted_count'  => $deletedCount,
            'error_count'    => $errorCount,
            'retention_days' => $days,
            'cutoff'         => $cutoff,
        ]);

        return IlluminateCommand::SUCCESS;
    }

    private function getAccessToken(): string
    {
        $tenantId = config('mail.graph.tenant_id');
        $clientId = config('mail.graph.client_id');
        $clientSecret = config('mail.graph.client_secret');

        $response = Http::asForm()->post("https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token", [
            'client_id'     => $clientId,
            'client_secret' => $clientSecret,
            'scope'         => 'https://graph.microsoft.com/.default',
            'grant_type'    => 'client_credentials',
        ]);

        if (! $response->successful()) {
            throw new Exception('Failed to get access token: '.$response->body());
        }

        return $response->json()['access_token'];
    }
}
