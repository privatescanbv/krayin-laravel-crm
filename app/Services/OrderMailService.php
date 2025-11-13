<?php

namespace App\Services;

use App\Models\Order;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Webkul\Contact\Models\Person;
use Webkul\EmailTemplate\Models\EmailTemplate;

class OrderMailService
{
    public const TEMPLATE_NAME = 'order mail';

    public function buildMailData(Order $order): array
    {
        $template = $this->ensureTemplateExists();

        $variables = $this->buildTemplateVariables($order);

        $subject = $this->interpolate($template->subject ?? '', $variables);
        $body = $this->interpolate($template->content ?? '', $variables);

        return [
            'subject'        => $subject,
            'body'           => $body,
            'default_email'  => $variables['default_email'] ?? null,
            'emails'         => $this->getEmailOptions($order),
            'template_id'    => $template->id,
        ];
    }

    public function getEmailOptions(Order $order): array
    {
        if (! $order->salesLead) {
            return [];
        }

        $emailOptions = [];
        $defaultEmail = $this->getDefaultEmail($order);

        $persons = $this->collectPersonsForSalesLead($order);

        foreach ($persons as $person) {
            $emails = $person->emails ?? [];

            foreach ($emails as $email) {
                $address = $email['value'] ?? null;

                if (! $address) {
                    continue;
                }

                $key = strtolower($address);

                if (! isset($emailOptions[$key])) {
                    $emailOptions[$key] = [
                        'value'      => $address,
                        'is_default' => false,
                    ];
                }

                if ($this->isTruthy($email['is_default'] ?? false)) {
                    $emailOptions[$key]['is_default'] = true;
                }

                if ($defaultEmail && strcasecmp($defaultEmail, $address) === 0) {
                    $emailOptions[$key]['is_default'] = true;
                }
            }
        }

        if (! empty($emailOptions) && ! $this->hasDefaultEmail($emailOptions) && $defaultEmail) {
            $defaultKey = strtolower($defaultEmail);

            if (isset($emailOptions[$defaultKey])) {
                $emailOptions[$defaultKey]['is_default'] = true;
            }
        }

        if (! empty($emailOptions) && ! $this->hasDefaultEmail($emailOptions)) {
            $firstKey = array_key_first($emailOptions);

            if ($firstKey !== null) {
                $emailOptions[$firstKey]['is_default'] = true;
            }
        }

        return array_values($emailOptions);
    }

    public function getDefaultEmail(Order $order): ?string
    {
        if (! $order->salesLead) {
            return null;
        }

        $default = $order->salesLead->defaultEmailContactPerson();

        if ($default) {
            return $default;
        }

        $persons = $this->collectPersonsForSalesLead($order);
        foreach ($persons as $person) {
            foreach ($person->emails ?? [] as $email) {
                if (! empty($email['value'])) {
                    return $email['value'];
                }
            }
        }

        return null;
    }

    protected function ensureTemplateExists(): EmailTemplate
    {
        return EmailTemplate::firstOrCreate(
            ['name' => self::TEMPLATE_NAME],
            [
                'subject' => 'Order {{ order_reference }} | {{ order_title }}',
                'content' => $this->defaultTemplateContent(),
            ]
        );
    }

    protected function defaultTemplateContent(): string
    {
        return <<<'HTML'
<p>Beste {{ customer_name }},</p>
<p>Hierbij ontvangt u de samenvatting van order {{ order_reference }} ({{ order_title }}).</p>
{{ order_summary_table }}
<p><strong>Totaalbedrag:</strong> {{ order_total }}</p>
{{ form_link_section }}
<p>{{ approval_instructions }}</p>
<p>Met vriendelijke groet,<br>{{ company_signature }}</p>
HTML;
    }

    protected function buildTemplateVariables(Order $order): array
    {
        $formLink = $this->createFormRequestAndGetLink($order);

        Log::info('OrderMailService: Form link generated', [
            'order_id'  => $order->id,
            'form_link' => $formLink,
        ]);

        // Always show the form link section (link is now always returned)
        $formLinkSection = '<p>Om uw order te kunnen verwerken, verzoeken wij u vriendelijk om <a href="'.e($formLink).'" style="color: #007bff; text-decoration: underline;">graag dit GVL formulier in te vullen</a>.</p>';

        return [
            'order_reference'      => (string) $order->id,
            'order_title'          => e($order->title ?? ''),
            'order_status'         => e($order->status?->label() ?? ''),
            'order_total'          => $this->formatCurrency($order->total_price),
            'order_summary_table'  => $this->renderItemsTable($order),
            'customer_name'        => e($this->resolveCustomerName($order)),
            'approval_instructions'=> 'Geef uw akkoord door op deze e-mail te reageren of telefonisch contact met ons op te nemen.',
            'company_signature'    => e(config('app.name', 'Privatescan')),
            'default_email'        => $this->getDefaultEmail($order),
            'current_date'         => Carbon::now()->format('d-m-Y'),
            'form_link'            => e($formLink),
            'form_link_section'    => $formLinkSection,
        ];
    }

    protected function renderItemsTable(Order $order): string
    {
        $items = $order->orderItems ?: collect();

        if (! $items instanceof Collection) {
            $items = collect($items);
        }

        if ($items->isEmpty()) {
            return '<p>Er zijn nog geen orderregels toegevoegd.</p>';
        }

        $rows = '';

        foreach ($items as $item) {
            $productName = e($item->product->name ?? 'Onbekend product');
            $quantity = (int) ($item->quantity ?? 0);
            $price = $this->formatCurrency($item->total_price ?? 0);
            $personName = e($item->person->name ?? '-');

            $rows .= sprintf(
                '<tr>'
                .'<td style="padding:8px; border-bottom:1px solid #e5e7eb;">%s</td>'
                .'<td style="padding:8px; text-align:center; border-bottom:1px solid #e5e7eb;">%s</td>'
                .'<td style="padding:8px; text-align:right; border-bottom:1px solid #e5e7eb;">%s</td>'
                .'<td style="padding:8px; border-bottom:1px solid #e5e7eb;">%s</td>'
                .'</tr>',
                $productName,
                $quantity,
                $price,
                $personName
            );
        }

        $rows .= sprintf(
            '<tr>'
            .'<td colspan="2" style="padding:8px; text-align:right; font-weight:600;">Totaal</td>'
            .'<td style="padding:8px; text-align:right; font-weight:600;">%s</td>'
            .'<td></td>'
            .'</tr>',
            $this->formatCurrency($order->total_price ?? 0)
        );

        return <<<HTML
<table style="width:100%; border-collapse:collapse; margin:16px 0;">
    <thead>
        <tr>
            <th style="text-align:left; padding:8px; border-bottom:2px solid #e5e7eb;">Product</th>
            <th style="text-align:center; padding:8px; border-bottom:2px solid #e5e7eb;">Aantal</th>
            <th style="text-align:right; padding:8px; border-bottom:2px solid #e5e7eb;">Prijs</th>
            <th style="text-align:left; padding:8px; border-bottom:2px solid #e5e7eb;">Voor</th>
        </tr>
    </thead>
    <tbody>
        {$rows}
    </tbody>
</table>
HTML;
    }

    protected function resolveCustomerName(Order $order): string
    {
        $person = $order->salesLead?->contactPerson;
        if (! $person) {
            // Fallback to the first person attached to the lead, if available
            $person = $order->salesLead?->lead?->persons()?->first();
        }

        if ($person && $person->name) {
            return $person->name;
        }

        return 'klant';
    }

    /**
     * Safely collect persons related to the sales lead without using the
     * salesLead->persons() many-to-many relation that may cause issues.
     */
    protected function collectPersonsForSalesLead(Order $order): Collection
    {
        $persons = Collection::make();

        if (! $order->salesLead) {
            return $persons;
        }

        if ($order->salesLead->contactPerson) {
            $persons->push($order->salesLead->contactPerson);
        }

        $leadPersons = $order->salesLead->lead?->persons()?->get();
        if ($leadPersons instanceof Collection && $leadPersons->isNotEmpty()) {
            foreach ($leadPersons as $p) {
                $persons->push($p);
            }
        }

        return $persons;
    }

    protected function formatCurrency($amount): string
    {
        $numeric = is_numeric($amount) ? (float) $amount : 0.0;

        return '€ '.number_format($numeric, 2, ',', '.');
    }

    protected function interpolate(string $template, array $variables): string
    {
        return preg_replace_callback('/\{\{\s*(.*?)\s*\}\}/', function ($matches) use ($variables) {
            $key = $matches[1];

            return array_key_exists($key, $variables) ? (string) $variables[$key] : $matches[0];
        }, $template) ?? $template;
    }

    protected function isTruthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        if (is_string($value)) {
            return in_array(strtolower($value), ['1', 'true', 'on', 'yes'], true);
        }

        return false;
    }

    protected function hasDefaultEmail(array $options): bool
    {
        foreach ($options as $option) {
            if (! empty($option['is_default'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Create a form request via the forms API and return the form link.
     * Uses existing link from sales lead if available, otherwise creates new one and saves it.
     */
    public function createFormRequestAndGetLink(Order $order): string
    {
        // Ensure salesLead is loaded
        if (! $order->salesLead) {
            $order->load('salesLead');
        }

        // Check if sales lead already has a form link (only create if empty)
        if ($order->salesLead && ! empty($order->salesLead->gvl_form_link)) {
            Log::info('OrderMailService: Using existing GVL form link', [
                'order_id'      => $order->id,
                'sales_lead_id' => $order->salesLead->id,
                'form_link'     => $order->salesLead->gvl_form_link,
            ]);

            return $order->salesLead->gvl_form_link;
        }

        // gvl_form_link is empty, create new form request
        Log::info('OrderMailService: gvl_form_link is empty, creating new form request', [
            'order_id'       => $order->id,
            'sales_lead_id'  => $order->salesLead?->id,
            'has_sales_lead' => ! is_null($order->salesLead),
        ]);

        try {
            Log::info('OrderMailService: Getting person for form', ['order_id' => $order->id]);
            $person = $this->getPersonForForm($order);
            if (! $person) {
                Log::error('OrderMailService: No person found for order', [
                    'order_id'           => $order->id,
                    'has_sales_lead'     => ! is_null($order->salesLead),
                    'has_contact_person' => ! is_null($order->salesLead?->contactPerson),
                    'has_lead'           => ! is_null($order->salesLead?->lead),
                ]);
                throw new Exception('No person found for order');
            }

            Log::info('OrderMailService: Person found, building form data', [
                'order_id'  => $order->id,
                'person_id' => $person->id,
            ]);

            $formData = $this->buildFormRequestData($order, $person);

            // Check if API token is configured
            $token = config('services.forms.api_token');
            if (empty($token)) {
                Log::error('OrderMailService: FORMS_API_KEY not configured', [
                    'order_id' => $order->id,
                ]);
                throw new Exception('FORMS_API_KEY is not configured. Please set FORMS_API_KEY in your .env file.');
            }

            Log::info('OrderMailService: Form data built, calling API', [
                'order_id'  => $order->id,
                'form_data' => $formData,
                'has_token' => ! empty($token),
            ]);

            $result = $this->createFormRequest($formData);
            $response = $result['response'] ?? null;
            $httpStatus = $result['status'] ?? null;

            Log::info('OrderMailService: API response received', [
                'order_id'           => $order->id,
                'http_status'        => $httpStatus,
                'has_response'       => ! is_null($response),
                'response_has_id'    => $response && isset($response['data']['id']),
                'response_structure' => $response ? ['has_data' => isset($response['data']), 'keys' => array_keys($response)] : null,
            ]);

            if (! $response || ! isset($response['data']['id'])) {
                $errorMessage = 'Failed to create form request';
                $errorDetails = [
                    'order_id'         => $order->id,
                    'http_status'      => $httpStatus,
                    'response'         => $response,
                    'response_keys'    => $response ? array_keys($response) : null,
                    'response_message' => $response['message'] ?? null,
                    'response_errors'  => $response['errors'] ?? null,
                ];

                Log::error('OrderMailService: Failed to create form request', $errorDetails);

                // Build a more descriptive error message
                if ($httpStatus === 200 && ! $response) {
                    // Status 200 but no valid JSON response usually means authentication failed
                    $errorMessage = 'Authentication failed: Forms API returned HTML login page instead of JSON. Please check FORMS_API_KEY configuration.';
                } elseif ($response && isset($response['message'])) {
                    $errorMessage .= ': '.$response['message'];
                } elseif ($response && isset($response['errors'])) {
                    $errorMessage .= ': '.json_encode($response['errors']);
                } elseif (! $response) {
                    $errorMessage .= ': API returned no response';
                    if ($httpStatus) {
                        $errorMessage .= " (HTTP {$httpStatus})";
                    }
                } else {
                    $errorMessage .= ': Response missing form request ID';
                }

                throw new Exception($errorMessage);
            }

            $formRequestId = $response['data']['id'];
            $frontendUrl = rtrim(config('services.forms.frontend_url', 'http://localhost:8001'), '/');
            $formLink = $response['form_url'] ?? '';
            $formLink = str_replace('http://forms/', $frontendUrl.'/', $formLink);

            // Save the link to the sales lead (one-time save)
            if ($order->salesLead) {
                $salesLead = $order->salesLead;

                Log::info('OrderMailService: Attempting to save GVL form link', [
                    'order_id'              => $order->id,
                    'sales_lead_id'         => $salesLead->id,
                    'form_request_id'       => $formRequestId,
                    'form_link'             => $formLink,
                    'current_gvl_form_link' => $salesLead->gvl_form_link,
                ]);

                // Use save() instead of update() for better control
                $salesLead->gvl_form_link = $formLink;
                $saved = $salesLead->save();

                if (! $saved) {
                    Log::error('OrderMailService: Failed to save GVL form link to sales', [
                        'order_id'      => $order->id,
                        'sales_lead_id' => $salesLead->id,
                        'form_link'     => $formLink,
                    ]);
                } else {
                    // Refresh the relation to ensure we have the updated value
                    $order->load('salesLead');

                    // Verify the save was successful
                    $salesLead->refresh();
                    $savedLink = $salesLead->gvl_form_link;

                    Log::info('OrderMailService: GVL form link saved to sales', [
                        'order_id'        => $order->id,
                        'sales_lead_id'   => $salesLead->id,
                        'form_request_id' => $formRequestId,
                        'form_link'       => $formLink,
                        'saved_link'      => $savedLink,
                        'save_successful' => ($savedLink === $formLink),
                    ]);
                }
            } else {
                Log::warning('OrderMailService: Cannot save GVL form link - no sales found', [
                    'order_id' => $order->id,
                ]);
            }

            return $formLink;
        } catch (Exception $e) {
            Log::error('OrderMailService: Exception creating form request', [
                'order_id'       => $order->id,
                'error'          => $e->getMessage(),
                'file'           => $e->getFile(),
                'line'           => $e->getLine(),
                'trace'          => $e->getTraceAsString(),
                'has_sales_lead' => ! is_null($order->salesLead),
                'sales_lead_id'  => $order->salesLead?->id,
            ]);

            // Re-throw the exception to break the flow
            throw $e;
        }
    }

    /**
     * Get the person to use for the form request.
     */
    protected function getPersonForForm(Order $order): ?Person
    {
        if (! $order->salesLead) {
            return null;
        }

        // Prefer contact person, fallback to first person from lead
        $person = $order->salesLead->contactPerson;
        if (! $person) {
            $person = $order->salesLead->lead?->persons()?->first();
        }

        return $person;
    }

    /**
     * Build the form request data array.
     *
     * @throws Exception if no default email could be found
     */
    protected function buildFormRequestData(Order $order, Person $person): array
    {
        $email = $person->findDefaultEmail();
        if (! $email) {
            throw new Exception('Person has no email address');
        }

        $birthday = $person->date_of_birth
            ? $person->date_of_birth->format('d-m-Y')
            : '01-01-1900'; // Fallback if birthday is missing (API requires it)

        // Determine MRI and CT scan needs based on order items
        $mriResearch = $this->hasMriResearch($order) ? 'Ja' : 'Nee';
        $ctScan = $this->hasCtScan($order) ? 'Ja' : 'Nee';

        // Use name if first_name/last_name are not available
        $firstName = $person->first_name ?? '';
        $lastName = $person->last_name ?? '';

        if (empty($firstName) && empty($lastName) && ! empty($person->name)) {
            // Try to split name if we only have full name
            $nameParts = explode(' ', $person->name, 2);
            $firstName = $nameParts[0] ?? '';
            $lastName = $nameParts[1] ?? $firstName;
        }

        return [
            'user_crm_id'     => $person->id,
            'user_firstname'  => $firstName ?: 'Onbekend',
            'user_lastname'   => $lastName ?: 'Onbekend',
            'user_maidenname' => ! empty($person->married_name) ? $person->married_name : '--',
            'user_email'      => $email,
            'user_birthday'   => $birthday,
            'mri_research'    => $mriResearch,
            'ct_scan'         => $ctScan,
        ];
    }

    /**
     * Check if order contains MRI research products.
     */
    protected function hasMriResearch(Order $order): bool
    {
        $items = $order->orderItems ?? collect();
        if (! $items instanceof Collection) {
            $items = collect($items);
        }

        foreach ($items as $item) {
            $productName = strtolower($item->product->name ?? '');
            if (str_contains($productName, 'mri')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if order contains CT scan products.
     */
    protected function hasCtScan(Order $order): bool
    {
        $items = $order->orderItems ?? collect();
        if (! $items instanceof Collection) {
            $items = collect($items);
        }

        foreach ($items as $item) {
            $productName = strtolower($item->product->name ?? '');
            if (str_contains($productName, 'ct') || str_contains($productName, 'ct-scan')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Make API call to create form request.
     *
     * @return array{status: int, response: array|null}
     */
    protected function createFormRequest(array $data): array
    {
        $apiUrl = rtrim(config('services.forms.api_url', 'http://forms'), '/');
        $token = config('services.forms.api_token');
        $url = "{$apiUrl}/api/forms";

        // Log request details
        Log::info('OrderMailService: Creating form request', [
            'url'           => $url,
            'method'        => 'POST',
            'request_body'  => $data,
            'has_token'     => ! empty($token),
            'token_preview' => $token ? substr($token, 0, 10).'...' : null,
        ]);

        // Build HTTP client with Bearer token for Sanctum
        $httpClient = Http::timeout(10);

        if ($token) {
            // Sanctum expects: Authorization: Bearer {token}
            $httpClient = $httpClient->withHeaders([
                'X-API-KEY' => $token,
                'Accept'    => 'application/json',
            ]);
        }

        $response = $httpClient->post($url, $data);

        // Log response details
        $status = $response->status();
        $responseBody = $response->body();
        $responseHeaders = $response->headers();

        // Check if response is HTML (likely a login page)
        $isHtml = $response->header('Content-Type') && str_contains($response->header('Content-Type'), 'text/html');
        $bodyPreview = strlen($responseBody) > 500 ? substr($responseBody, 0, 500).'...' : $responseBody;

        $responseData = [
            'status'                => $status,
            'content_type'          => $response->header('Content-Type'),
            'is_html'               => $isHtml,
            'response_headers'      => $responseHeaders,
            'response_body_preview' => $bodyPreview,
            'response_body_length'  => strlen($responseBody),
        ];

        // If we get HTML back (login page), it means authentication failed
        if ($isHtml || ($status === 200 && str_contains($responseBody, '<html'))) {
            Log::error('OrderMailService: Forms API returned HTML (likely login page)', [
                'status'       => $status,
                'content_type' => $response->header('Content-Type'),
                'body_preview' => $bodyPreview,
                'message'      => 'Authentication failed - received HTML login page instead of JSON',
            ]);

            return [
                'status'   => $status,
                'response' => null,
            ];
        }

        if (! $response->successful()) {
            Log::error('OrderMailService: Forms API error', $responseData);

            return [
                'status'   => $status,
                'response' => null,
            ];
        }

        // Try to parse JSON response
        $jsonResponse = null;
        try {
            $jsonResponse = $response->json();

            // Check if json() returned null (empty body or invalid JSON)
            if ($jsonResponse === null && ! empty($responseBody)) {
                Log::warning('OrderMailService: JSON response is null but body is not empty', [
                    'status'       => $status,
                    'body_length'  => strlen($responseBody),
                    'body_preview' => substr($responseBody, 0, 200),
                ]);
            }
        } catch (Exception $e) {
            Log::error('OrderMailService: Failed to parse JSON response', [
                'status'      => $status,
                'body'        => $responseBody,
                'body_length' => strlen($responseBody ?? ''),
                'error'       => $e->getMessage(),
            ]);

            return [
                'status'   => $status,
                'response' => null,
            ];
        }

        Log::info('OrderMailService: Forms API success', array_merge($responseData, [
            'json_response'      => $jsonResponse,
            'json_response_type' => gettype($jsonResponse),
            'has_data_key'       => isset($jsonResponse['data']),
            'has_data_id'        => isset($jsonResponse['data']['id']),
            'response_keys'      => is_array($jsonResponse) ? array_keys($jsonResponse) : null,
        ]));

        return [
            'status'   => $status,
            'response' => $jsonResponse,
        ];
    }
}
