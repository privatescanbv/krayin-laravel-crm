<?php

namespace App\Services;

use Exception;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FormService
{
    /**
     * Get form status from forms API.
     *
     * @throws Exception
     */
    public function getFormStatus(int $formId): array
    {
        $url = $this->buildApiUrl("/api/forms/{$formId}/status");

        Log::info('FormService: Fetching form status', [
            'form_id'    => $formId,
            'status_url' => $url,
        ]);

        $response = $this->makeRequest('get', $url, ['form_id' => $formId, 'url' => $url]);
        $result = $this->parseResponse($response, ['form_id' => $formId, 'url' => $url]);

        if ($result['status'] !== 200) {
            throw new Exception($result['json']['message'] ?? 'Fout bij ophalen formulier status.');
        }

        Log::info('FormService: Form status retrieved successfully', [
            'form_id' => $formId,
            'status'  => $result['status'],
        ]);

        return $result['json'] ?? [];
    }

    /**
     * Create a form request via the forms API.
     *
     * @param  array  $data  Form data to send
     * @return array{status: int, response: array|null}
     *
     * @throws Exception
     */
    public function createFormRequest(array $data): array
    {
        $url = $this->buildApiUrl('/api/forms');

        Log::info('FormService: Creating form request', [
            'url'           => $url,
            'method'        => 'POST',
            'request_body'  => $data,
        ]);

        $response = $this->makeRequest('post', $url, ['url' => $url], $data);
        $result = $this->parseResponse($response, ['url' => $url], true);

        // Check for HTML response (authentication failure)
        if ($result['is_html']) {
            return [
                'status'   => $result['status'],
                'response' => null,
            ];
        }

        if (! $response->successful()) {
            return [
                'status'   => $result['status'],
                'response' => null,
            ];
        }

        Log::info('FormService: Forms API success', [
            'status'         => $result['status'],
            'has_data_key'   => isset($result['json']['data']),
            'has_data_id'    => isset($result['json']['data']['id']),
            'response_keys'  => is_array($result['json']) ? array_keys($result['json']) : null,
        ]);

        return [
            'status'   => $result['status'],
            'response' => $result['json'],
        ];
    }

    /**
     * Delete a form via the forms API.
     *
     * @return array{status: int, response: array|null}
     *
     * @throws Exception
     */
    public function deleteForm(int $formId): array
    {
        $url = $this->buildApiUrl("/api/forms/{$formId}");

        Log::info('FormService: Deleting form', [
            'form_id'    => $formId,
            'delete_url' => $url,
        ]);

        $response = $this->makeRequest('delete', $url, ['form_id' => $formId, 'url' => $url]);
        $result = $this->parseResponse($response, ['form_id' => $formId, 'url' => $url]);

        if ($result['status'] !== 200) {
            Log::warning('FormService: Forms API error', [
                'form_id'       => $formId,
                'delete_url'    => $url,
                'status'        => $result['status'],
                'response_json' => $result['json'],
            ]);
        } else {
            Log::info('FormService: Form deleted successfully', [
                'form_id' => $formId,
            ]);
        }

        return [
            'status'   => $result['status'],
            'response' => $result['json'],
        ];
    }

    /**
     * Extract form ID from GVL form link URL.
     */
    public function extractFormIdFromUrl(?string $gvlFormLink): ?int
    {
        if (empty($gvlFormLink)) {
            return null;
        }

        // Pattern 1: 'forms/3/step/1' or 'forms/3'
        if (preg_match('#forms/(\d+)(?:/step|/|$)#', $gvlFormLink, $m)) {
            return (int) $m[1];
        }

        // Pattern 2: Try to extract from API response ID if stored differently
        if (preg_match('#/(\d+)(?:/step|/|$)#', $gvlFormLink, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    /**
     * Build API URL from path.
     */
    protected function buildApiUrl(string $path): string
    {
        $apiUrl = rtrim(config('services.forms.api_url', 'http://forms'), '/');

        return $apiUrl.$path;
    }

    /**
     * Validate and get API token.
     *
     * @param  array  $context  Additional context for logging
     *
     * @throws Exception
     */
    protected function getApiToken(array $context = []): string
    {
        $token = config('services.forms.api_token');

        if (empty($token)) {
            Log::error('FormService: FORMS_API_TOKEN not configured', $context);
            throw new Exception('FORMS_API_TOKEN is not configured. Please set FORMS_API_TOKEN in your .env file.');
        }

        return $token;
    }

    /**
     * Create HTTP client with authentication.
     *
     * @throws Exception
     */
    protected function createHttpClient(): \Illuminate\Http\Client\PendingRequest
    {
        $token = $this->getApiToken();

        return Http::timeout(10)
            ->acceptJson()
            ->withHeaders([
                'X-API-KEY' => $token,
            ]);
    }

    /**
     * Make HTTP request to Forms API.
     *
     * @param  string  $method  HTTP method (get, post, delete, etc.)
     * @param  string  $url  Full URL
     * @param  array  $context  Context for logging
     * @param  array|null  $data  Request data (for POST requests)
     *
     * @throws Exception
     */
    protected function makeRequest(string $method, string $url, array $context = [], ?array $data = null): Response
    {
        $httpClient = $this->createHttpClient();

        try {
            if ($data !== null) {
                return $httpClient->{$method}($url, $data);
            }

            return $httpClient->{$method}($url);
        } catch (Exception $exception) {
            Log::error('FormService: Could not reach Forms API', array_merge($context, [
                'message' => $exception->getMessage(),
            ]));

            throw new Exception('Forms API kon niet worden bereikt: '.$exception->getMessage());
        }
    }

    /**
     * Parse HTTP response and extract JSON.
     *
     * @param  array  $context  Context for logging
     * @param  bool  $checkHtml  Whether to check for HTML responses (login pages)
     * @return array{status: int, json: array|null, is_html: bool}
     */
    protected function parseResponse(Response $response, array $context = [], bool $checkHtml = false): array
    {
        $status = $response->status();
        $body = $response->body();
        $json = null;
        $isHtml = false;

        // Check if response is HTML (likely a login page)
        if ($checkHtml) {
            $contentType = $response->header('Content-Type');
            $isHtml = ($contentType && str_contains($contentType, 'text/html'))
                || ($status === 200 && str_contains($body, '<html'));

            if ($isHtml) {
                Log::error('FormService: Forms API returned HTML (likely login page)', array_merge($context, [
                    'status'       => $status,
                    'content_type' => $contentType,
                    'body_preview' => strlen($body) > 500 ? substr($body, 0, 500).'...' : $body,
                    'message'      => 'Authentication failed - received HTML login page instead of JSON',
                ]));
            }
        }

        // Try to parse JSON response
        try {
            $json = $response->json();

            // Check if json() returned null (empty body or invalid JSON)
            if ($json === null && ! empty($body)) {
                Log::warning('FormService: JSON response is null but body is not empty', array_merge($context, [
                    'status'       => $status,
                    'body_length'  => strlen($body),
                    'body_preview' => substr($body, 0, 200),
                ]));
            }
        } catch (Exception $e) {
            Log::warning('FormService: Could not parse JSON response', array_merge($context, [
                'status'       => $status,
                'body_preview' => strlen($body) > 500 ? substr($body, 0, 500).'...' : $body,
                'error'        => $e->getMessage(),
            ]));
        }

        return [
            'status'  => $status,
            'json'    => $json,
            'is_html' => $isHtml,
        ];
    }
}
