<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebhookService
{
    /**
     * Send a webhook notification with custom data.
     */
    public function sendWebhook(array $data): bool
    {
        try {
            Log::info('Send webhook', $data);
            $response = Http::post(config('webhook.base_url'), array_merge($data, [
                'timestamp' => now()->toIso8601String(),
            ]));

            if ($response->successful()) {
                Log::info('Webhook sent successfully', $data);

                return true;
            }

            Log::error('Webhook failed', [
                'data'     => $data,
                'response' => $response->body(),
            ]);

            return false;
        } catch (Exception $e) {
            Log::error('Exception while sending webhook', [
                'data'  => $data,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
