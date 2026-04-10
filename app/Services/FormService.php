<?php

namespace App\Services;

use App\Enums\FormStatus;
use App\Models\Anamnesis;
use Exception;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Webkul\Lead\Models\Lead;

/**
 * Patient form service, as GVL
 */
class FormService
{
    public function __construct(
        readonly private LeadAndSalesService $leadAndSalesService
    ) {}

    public function getOpenForms(int $patientId): int
    {
        $url = $this->buildApiUrl("/api/forms/$patientId/open/count");

        Log::info('FormService: Fetching form open count', [
            'patient'    => $patientId,
            'url'        => $url,
        ]);

        $response = $this->makeRequest('get', $url, ['url' => $url]);
        $result = $this->parseResponse($response, ['crm_person_id' => $patientId, 'url' => $url]);

        if ($result['status'] !== 200) {
            throw new Exception($result['json']['message'] ?? 'Could not get form count for patient.');
        }
        $openFormCount = $result['json']['open_forms_count'] ?? 0;

        Log::info('FormService: Form open count retrieved successfully', [
            'open_forms_count'  => $openFormCount,
        ]);

        return $openFormCount;
    }

    /**
     * Get form status from forms API.
     *
     * @return array{
     *      entity_type: string,
     *      id: int,
     *      action: string,
     *      status: string,
     *      url: string,
     *      payload: array{
     *          form_status: string,
     *          previous_status: string,
     *          form_response_id: int,
     *          form_request_id: int
     *      }
     *  }
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

    public function getFormStatusAsString(int $formId): FormStatus
    {
        $status = $this->getFormStatus($formId)['status'];

        return FormStatus::mapFrom($status);
    }

    /**
     * Create a diagnosis form for a lead via the forms API.
     *
     * @param  array  $fields  Question/answer pairs from the form submission
     * @return array{status: int, response: array|null}
     *
     * @throws Exception
     */
    public function createDiagnosisFormForLead(Lead $lead, array $fields): array
    {

        $email = $lead->findDefaultEmail();
        if (! $email) {
            throw new Exception('Lead has no email address.');
        }

        $formData = [
            'lead_id'         => (string) $lead->id,
            'firstname'       => $lead->first_name ?: '-',
            'lastname'        => $lead->last_name ?: '-',
            'fields'          => $fields,
        ];

        $url = $this->buildApiUrl('/api/leads/forms');

        Log::info('FormService: Creating diagnosis form for lead', [
            'lead_id'      => $lead->id,
            'url'          => $url,
            'request_body' => $formData,
        ]);

        $response = $this->makeRequest('post', $url, ['lead_id' => $lead->id, 'url' => $url], $formData);
        $result = $this->parseResponse($response, ['lead_id' => $lead->id, 'url' => $url], true);

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

        Log::info('FormService: Diagnosis form created for lead', [
            'lead_id'       => $lead->id,
            'status'        => $result['status'],
            'has_data_key'  => isset($result['json']['data']),
            'has_data_id'   => isset($result['json']['data']['id']),
        ]);

        return [
            'status'   => $result['status'],
            'response' => $result['json'],
        ];
    }

    /**
     * Download a form PDF via the forms API.
     *
     * @return Response The raw HTTP response from the forms API
     *
     * @throws Exception
     */
    public function downloadForm(int $formId): Response
    {
        $url = $this->buildApiUrl("/api/forms/{$formId}/download");

        return $this->makeRequest('get', $url, ['form_id' => $formId, 'url' => $url]);
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

        try {
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
        } catch (Exception $exception) {
            Log::error('Could not detach GVL from', ['error' => $exception->getMessage()]);

            return [
                'status'   => 400,
                'response' => $exception->getMessage(),
            ];
        }
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
    public function buildApiUrl(string $path): string
    {
        $apiUrl = rtrim(config('services.portal.patient.api_url', 'http://forms'), '/');

        return $apiUrl.$path;
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
    public function makeRequest(string $method, string $url, array $context = [], ?array $data = null): Response
    {
        $httpClient = $this->createHttpClient();

        try {
            Log::info('FormService: make request with data', array_merge($context, [
                'method' => strtoupper($method),
                'url'    => $url,
                'data'   => $data ?? 'N/A',
            ]));
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
    public function parseResponse(Response $response, array $context = [], bool $checkHtml = false): array
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

    public function findRelatedEntityByFormId(string $showFormUrl): array
    {
        $person = Anamnesis::select('person_id')
            ->where('gvl_form_link', $showFormUrl)
            ->firstOrFail();

        $result = $this->leadAndSalesService->findOpenByPerson($person->person_id);

        return [
            'lead'      => $result['lead'] ?? null,
            'sales'     => $result['sales'] ?? null,
            'person_id' => $person->person_id,
        ];
    }

    public function removeSessionForPerson(string $personId): void
    {
        $formData = ['crm_person_id' => $personId];

        $url = $this->buildApiUrl('/api/sessions/invalidate');

        Log::info('FormService: removing session for person', [
            'url'           => $url,
            'method'        => 'POST',
            'body'          => $formData,
        ]);

        $response = $this->makeRequest('post', $url, ['url' => $url], $formData);
        $result = $this->parseResponse($response, ['url' => $url], true);

        if (! $response->successful()) {
            Log::error('FormService: Could not remove session for person', ['status' => $result['status'], 'response' => $result['json']]);
        } else {

            Log::info('FormService: Eemoving session for person success', [
                'status'         => $result['status'],
            ]);
        }
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
        $token = config('services.portal.patient.api_token');

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
    protected function createHttpClient(): PendingRequest
    {
        $token = $this->getApiToken();

        return Http::timeout(10)
            ->acceptJson()
            ->withHeaders([
                'X-API-KEY' => $token,
            ]);
    }
}
