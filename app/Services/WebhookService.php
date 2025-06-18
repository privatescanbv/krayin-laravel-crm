<?php

namespace App\Services;

use App\Enums\WebhookType;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebhookService
{
    /**
     * Send a webhook notification with custom data.
     */
    public function sendWebhook(array $data, WebhookType $webhookType): bool
    {
        try {
            Log::info('Send webhook', $data);

            $url = $this->getWebhookUrl($webhookType);

            $response = Http::post($url, array_merge($data, [
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

    /**
     * @throws Exception
     */
    private function getWebhookUrl(WebhookType $webhookType): string
    {
        $endpoint = config('webhook.endpoints')[$webhookType->value] ?? null;
        if (! $endpoint) {
            throw new Exception("Webhook endpoint not configured for type: {$webhookType->value}");
        }

        return config('webhook.base_url').$endpoint;
    }
}
