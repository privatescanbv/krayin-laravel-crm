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
    public function sendWebhook(array $data, WebhookType $webhookType, string $caller = 'unknown'): bool
    {
        // Check if webhooks are globally disabled
        if (! config('webhook.enabled', true)) {
            Log::info('Webhooks are disabled - skipping webhook: '.$webhookType->value.'; from: '.$caller, $data);

            return true; // Return true to indicate "successful" skip
        }

        $url = '';
        $body = '';
        $hasN8nApiKey = false;
        try {
            Log::info('Send webhook: '.$webhookType->value.'; from: '.$caller, $data);
            $hasN8nApiKey = ! empty(config('webhook.n8n_api_key'));
            $url = $this->getWebhookUrl($webhookType);

            $body = array_merge($data, [
                'timestamp' => now()->toIso8601String(),
            ]);
            $request = Http::asJson();

            if ($hasN8nApiKey) {
                $request = $request->withHeaders([
                    'X-N8N-API-KEY' => config('webhook.n8n_api_key'),
                ]);
            }
            $response = $request->post($url, $body);

            if (! $response->successful()) {
                Log::error('Webhook failed', [
                    'has_n8n_api_key' => $hasN8nApiKey,
                    'headers'         => ['X-N8N-API-KEY' => config('webhook.n8n_api_key')],
                    'data'            => $data,
                    'url'             => $url,
                    'response'        => $response->body(),
                ]);

                return false;
            }

            return true;
        } catch (Exception $e) {
            Log::error('Exception while sending webhook', [
                'has_n8n_api_key' => $hasN8nApiKey,
                'headers'         => ['X-N8N-API-KEY' => config('webhook.n8n_api_key')],
                'url'             => $url,
                'body'            => $body,
                'error'           => $e->getMessage(),
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
