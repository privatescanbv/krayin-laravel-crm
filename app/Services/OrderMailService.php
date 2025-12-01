<?php

namespace App\Services;

use App\Models\Anamnesis;
use App\Models\Order;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Webkul\Contact\Models\Person;
use Webkul\EmailTemplate\Models\EmailTemplate;

class OrderMailService
{
    public const TEMPLATE_NAME = 'order mail';

    public function __construct(
        protected FormService $formService
    ) {}

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

    /**
     * Create a form request via the forms API and return the form link.
     * Uses existing link from anamnesis if available, otherwise creates new one and saves it.
     *
     * @deprecated This method is deprecated. Use AnamnesisController methods instead.
     * For mail attachments, use getAnamnesisGvlFormLinks() instead.
     */
    public function createFormRequestAndGetLink(Order $order): string
    {
        // Ensure salesLead is loaded
        if (! $order->salesLead) {
            $order->load('salesLead');
        }

        // Get first person from sales lead
        $person = $this->getPersonForForm($order);
        if (! $person || ! $order->salesLead->lead_id) {
            throw new Exception('No person or lead found for order');
        }

        // Check if anamnesis already has a form link
        $anamnesis = Anamnesis::where('lead_id', $order->salesLead->lead_id)
            ->where('person_id', $person->id)
            ->first();

        if ($anamnesis && ! empty($anamnesis->gvl_form_link)) {
            Log::info('OrderMailService: Using existing GVL form link from anamnesis', [
                'order_id'      => $order->id,
                'anamnesis_id'  => $anamnesis->id,
                'person_id'     => $person->id,
                'form_link'     => $anamnesis->gvl_form_link,
            ]);

            return $anamnesis->gvl_form_link;
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

            $result = $this->formService->createFormRequest($order, $person);
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
            //            $frontendUrl = rtrim(config('services.portal.patient'), '/');
            $formLink = $response['form_url'] ?? throw new Exception('Missing form_url in API response');
            //            $formLink = str_replace('http://forms/', $frontendUrl.'/', $formLink);

            // Save the link to the anamnesis (one-time save)
            if ($anamnesis) {
                Log::info('OrderMailService: Attempting to save GVL form link to anamnesis', [
                    'order_id'              => $order->id,
                    'anamnesis_id'          => $anamnesis->id,
                    'person_id'             => $person->id,
                    'form_request_id'       => $formRequestId,
                    'form_link'             => $formLink,
                    'current_gvl_form_link' => $anamnesis->gvl_form_link,
                ]);

                $anamnesis->gvl_form_link = $formLink;
                $saved = $anamnesis->save();

                if (! $saved) {
                    Log::error('OrderMailService: Failed to save GVL form link to anamnesis', [
                        'order_id'     => $order->id,
                        'anamnesis_id' => $anamnesis->id,
                        'form_link'    => $formLink,
                    ]);
                } else {
                    Log::info('OrderMailService: GVL form link saved to anamnesis', [
                        'order_id'        => $order->id,
                        'anamnesis_id'    => $anamnesis->id,
                        'form_request_id' => $formRequestId,
                        'form_link'       => $formLink,
                    ]);
                }
            } else {
                Log::warning('OrderMailService: Cannot save GVL form link - no anamnesis found', [
                    'order_id'  => $order->id,
                    'person_id' => $person->id,
                    'lead_id'   => $order->salesLead->lead_id,
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

    protected function ensureTemplateExists(): EmailTemplate
    {
        $template = EmailTemplate::firstOrCreate(
            ['name' => self::TEMPLATE_NAME],
            [
                'subject' => 'Order {{ order_reference }} | {{ order_title }}',
                'content' => $this->defaultTemplateContent(),
            ]
        );

        // Update template with latest content if it already existed
        if ($template->wasRecentlyCreated === false) {
            $template->update([
                'subject' => 'Order {{ order_reference }} | {{ order_title }}',
                'content' => $this->defaultTemplateContent(),
            ]);
        }

        return $template;
    }

    protected function defaultTemplateContent(): string
    {
        return <<<'HTML'
<p>Beste {{ customer_name }},</p>
<p>Hierbij bevestigen wij uw afspraak(en) voor order {{ order_reference }} ({{ order_title }}).</p>
{{ appointments_by_person }}
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

        // Load order items with resource planner data
        $order->load([
            'orderItems.resourceOrderItems.resource.clinic.address',
            'orderItems.resourceOrderItems.resource.resourceType',
            'orderItems.person',
            'orderItems.product',
        ]);

        return [
            'order_reference'        => (string) $order->id,
            'order_title'            => e($order->title ?? ''),
            'order_status'           => e($order->status?->label() ?? ''),
            'order_total'            => $this->formatCurrency($order->total_price),
            'order_summary_table'    => $this->renderItemsTable($order),
            'appointments_by_person' => $this->renderAppointmentsByPerson($order),
            'customer_name'          => e($this->resolveCustomerName($order)),
            'approval_instructions'  => 'Geef uw akkoord door op deze e-mail te reageren of telefonisch contact met ons op te nemen.',
            'company_signature'      => e(config('app.name', 'Privatescan')),
            'default_email'          => $this->getDefaultEmail($order),
            'current_date'           => Carbon::now()->format('d-m-Y'),
            'form_link'              => e($formLink),
            'form_link_section'      => $formLinkSection,
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

    protected function renderAppointmentsByPerson(Order $order): string
    {
        $items = $order->orderItems ?: collect();

        if (! $items instanceof Collection) {
            $items = collect($items);
        }

        if ($items->isEmpty()) {
            return '<p>Er zijn nog geen afspraken ingepland.</p>';
        }

        // Group appointments by person
        $appointmentsByPerson = [];

        foreach ($items as $item) {
            $personId = $item->person_id;
            $personName = $item->person->name ?? 'Onbekend';

            if (! isset($appointmentsByPerson[$personId])) {
                $appointmentsByPerson[$personId] = [
                    'person_name'  => $personName,
                    'appointments' => [],
                ];
            }

            // Get resource order items (appointments) for this order item
            $resourceOrderItems = $item->resourceOrderItems ?? collect();
            if (! $resourceOrderItems instanceof Collection) {
                $resourceOrderItems = collect($resourceOrderItems);
            }

            foreach ($resourceOrderItems as $resourceOrderItem) {
                $resource = $resourceOrderItem->resource;
                $clinic = $resource?->clinic;
                $address = $clinic?->address;

                $from = $resourceOrderItem->from ? Carbon::parse($resourceOrderItem->from) : null;
                $to = $resourceOrderItem->to ? Carbon::parse($resourceOrderItem->to) : null;

                $appointmentData = [
                    'product_name'  => e($item->product->name ?? 'Onbekend product'),
                    'resource_name' => e($resource->name ?? 'Onbekend'),
                    'date'          => $from ? $from->format('d-m-Y') : 'N/A',
                    'time_from'     => $from ? $from->format('H:i') : 'N/A',
                    'time_to'       => $to ? $to->format('H:i') : 'N/A',
                    'clinic_name'   => e($clinic->name ?? 'Onbekend'),
                    'address'       => $this->formatAddress($address),
                ];

                $appointmentsByPerson[$personId]['appointments'][] = $appointmentData;
            }
        }

        // Render HTML for each person
        $html = '';

        foreach ($appointmentsByPerson as $personData) {
            if (empty($personData['appointments'])) {
                continue;
            }

            $html .= '<div style="margin: 20px 0; padding: 15px; background-color: #f9fafb; border-left: 4px solid #3b82f6; border-radius: 4px;">';
            $html .= '<h3 style="margin-top: 0; margin-bottom: 15px; color: #111827; font-size: 18px;">Afspraken voor '.e($personData['person_name']).'</h3>';

            foreach ($personData['appointments'] as $appointment) {
                $html .= '<div style="margin-bottom: 15px; padding: 12px; background-color: #ffffff; border-radius: 4px; border: 1px solid #e5e7eb;">';
                $html .= '<p style="margin: 0 0 8px 0; font-weight: 600; color: #111827;">'.$appointment['product_name'].'</p>';
                $html .= '<p style="margin: 4px 0; color: #6b7280;"><strong>Datum:</strong> '.$appointment['date'].'</p>';
                $html .= '<p style="margin: 4px 0; color: #6b7280;"><strong>Tijd:</strong> '.$appointment['time_from'].' - '.$appointment['time_to'].'</p>';
                $html .= '<p style="margin: 4px 0; color: #6b7280;"><strong>Locatie:</strong> '.$appointment['clinic_name'].'</p>';

                if ($appointment['address']) {
                    $html .= '<p style="margin: 4px 0; color: #6b7280;"><strong>Adres:</strong> '.$appointment['address'].'</p>';
                }

                $html .= '<p style="margin: 4px 0; color: #6b7280;"><strong>Resource:</strong> '.$appointment['resource_name'].'</p>';
                $html .= '</div>';
            }

            $html .= '</div>';
        }

        if (empty($html)) {
            return '<p>Er zijn nog geen afspraken ingepland.</p>';
        }

        return $html;
    }

    protected function formatAddress($address): string
    {
        if (! $address) {
            return '';
        }

        $parts = array_filter([
            trim($address->street ?? ''),
            trim(($address->house_number ?? '').($address->house_number_suffix ?? '')),
            trim($address->postal_code ?? ''),
            trim($address->city ?? ''),
        ]);

        return implode(' ', $parts);
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

            return array_key_exists($key, $variables)
                ? \App\Helpers\ValueNormalizer::toString($variables[$key])
                : $matches[0];
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
     * Get the person to use for the form request.
     */
    protected function getPersonForForm(Order $order): ?Person
    {
        // Prefer contact person, fallback to first person from lead
        $person = $order->salesLead->contactPerson;
        if (! $person) {
            $person = $order->salesLead->lead?->persons()?->first();
        }

        return $person;
    }
}
