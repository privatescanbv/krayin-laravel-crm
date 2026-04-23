<?php

namespace App\Services;

use App\Enums\EmailTemplateCode;
use App\Helpers\ValueNormalizer;
use App\Models\Address;
use App\Models\Anamnesis;
use App\Models\Order;
use App\Services\Mail\CrmMailService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Webkul\Contact\Models\Person;
use Webkul\EmailTemplate\Models\EmailTemplate;

class OrderMailService
{
    public function __construct(
        private readonly CrmMailService $crmMailService
    ) {}

    public function buildMailData(Order $order, ?Person $person = null): array
    {
        $template = EmailTemplate::byCodeEnum(EmailTemplateCode::ACKNOWLEDGE_ORDER_MAIL)->firstOrFail();

        $variables = $this->buildTemplateVariables($order, $person);

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

    /**
     * Variables for the acknowledge-order-mail template (admin template API + preview parity).
     *
     * @return array<string, mixed>
     */
    public function templateVariablesForAcknowledgeOrder(Order $order, ?Person $person = null): array
    {
        return $this->buildTemplateVariables($order, $person);
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

    protected function buildTemplateVariables(Order $order, ?Person $person = null): array
    {
        $formLink = $this->getExistingFormLink($order);
        $formLinkSection = '';

        if (! empty($formLink)) {
            $safeLink = e($formLink);
            $formLinkSection = '<p>Om uw order te kunnen verwerken, verzoeken wij u vriendelijk om <a href="'.$safeLink.'" style="color: #007bff; text-decoration: underline;">graag dit GVL formulier in te vullen</a>.</p>';
        }

        $order->load([
            'orderItems.resourceOrderItems.resource.clinic.address',
            'orderItems.resourceOrderItems.resource.resourceType',
            'orderItems.person',
            'orderItems.product',
            'organization.address',
            'salesLead.lead.organization.address',
            'salesLead.contactPerson.address',
        ]);

        $personId = $person?->id;

        return array_merge([
            'order_reference'        => (string) $order->id,
            'order_title'            => e($order->title ?? ''),
            'order_status'           => e($order->status?->label() ?? ''),
            'order_total'            => $this->formatCurrency($order->total_price),
            'order_summary_table'    => $this->renderItemsTable($order, $personId),
            'appointments_by_person' => $this->renderAppointmentsByPerson($order, $personId),
            'customer_name'          => $person ? e($person->name) : e($this->resolveCustomerName($order)),
            'approval_instructions'  => 'Geef uw akkoord door op deze e-mail te reageren of telefonisch contact met ons op te nemen.',
            'company_signature'      => e(config('app.name', 'Privatescan')),
            'default_email'          => $this->getDefaultEmail($order),
            'current_date'           => Carbon::now()->format('d-m-Y'),
            'form_link'              => $formLink ? e($formLink) : '',
            'form_link_section'      => $formLinkSection,
        ], $this->buildAddressVariables($order->resolveAddress($person)));
    }

    protected function buildAddressVariables(?Address $address): array
    {
        if (! $address) {
            return array_fill_keys([
                'address_street', 'address_house_number', 'address_house_number_suffix',
                'address_postal_code', 'address_city', 'address_state', 'address_country',
                'address_line1', 'address_line2', 'address_full',
            ], '');
        }

        $suffix = $address->house_number_suffix ? ' '.$address->house_number_suffix : '';
        $line1 = trim(($address->street ?? '').' '.($address->house_number ?? '').$suffix);
        $postalFormatted = $this->formatPostalCodeForDisplay($address->postal_code ?? '');
        $line2 = trim($postalFormatted.' '.($address->city ?? ''));
        $line3 = trim((string) ($address->country ?? ''));
        $fullLines = array_values(array_filter([$line1, $line2, $line3], fn (string $s): bool => $s !== ''));
        $addressFull = implode('', array_map(
            static fn (string $s): string => '<span style="display:block;">'.e($s).'</span>',
            $fullLines,
        ));

        return [
            'address_street'              => $address->street ?? '',
            'address_house_number'        => $address->house_number ?? '',
            'address_house_number_suffix' => $address->house_number_suffix ?? '',
            'address_postal_code'         => $postalFormatted,
            'address_city'                => $address->city ?? '',
            'address_state'               => $address->state ?? '',
            'address_country'             => $address->country ?? '',
            'address_line1'               => $line1,
            'address_line2'               => $line2,
            'address_full'                => $addressFull,
        ];
    }

    /**
     * Return an existing GVL form link from anamnesis, if available.
     * This method has no side effects (no external API calls).
     */
    protected function getExistingFormLink(Order $order): ?string
    {
        // Ensure salesLead is loaded
        if (! $order->salesLead) {
            $order->load('salesLead');
        }

        $person = $this->getPersonForForm($order);
        $leadId = $order->salesLead?->lead_id;

        if (! $person || ! $leadId) {
            return null;
        }

        $anamnesis = Anamnesis::where('lead_id', $leadId)
            ->where('person_id', $person->id)
            ->first();

        if ($anamnesis && ! empty($anamnesis->gvl_form_link)) {
            return $anamnesis->gvl_form_link;
        }

        return null;
    }

    protected function renderItemsTable(Order $order, ?int $filterPersonId = null): string
    {
        $items = $order->orderItems ?: collect();

        if (! $items instanceof Collection) {
            $items = collect($items);
        }

        if ($filterPersonId !== null) {
            $items = $items->where('person_id', $filterPersonId)->values();
        }

        if ($items->isEmpty()) {
            return '<p>Er zijn nog geen orderregels toegevoegd.</p>';
        }

        $rows = '';
        $showPersonColumn = $filterPersonId === null;

        foreach ($items as $item) {
            $productName = e($item->product->name ?? 'Onbekend product');
            $quantity = (int) ($item->quantity ?? 0);
            $price = $this->formatCurrency($item->total_price ?? 0);

            $rows .= '<tr>'
                .'<td style="padding:8px; border-bottom:1px solid #e5e7eb;">'.$productName.'</td>'
                .'<td style="padding:8px; text-align:center; border-bottom:1px solid #e5e7eb;">'.$quantity.'</td>'
                .'<td style="padding:8px; text-align:right; border-bottom:1px solid #e5e7eb;">'.$price.'</td>';

            if ($showPersonColumn) {
                $rows .= '<td style="padding:8px; border-bottom:1px solid #e5e7eb;">'.e($item->person->name ?? '-').'</td>';
            }

            $rows .= '</tr>';
        }

        $totalPrice = $filterPersonId !== null
            ? $this->formatCurrency($items->sum('total_price'))
            : $this->formatCurrency($order->total_price ?? 0);

        $colspanTotal = $showPersonColumn ? 2 : 2;

        $rows .= '<tr>'
            .'<td colspan="'.$colspanTotal.'" style="padding:8px; text-align:right; font-weight:600;">Totaal</td>'
            .'<td style="padding:8px; text-align:right; font-weight:600;">'.$totalPrice.'</td>';

        if ($showPersonColumn) {
            $rows .= '<td></td>';
        }

        $rows .= '</tr>';

        $personHeader = $showPersonColumn
            ? '<th style="text-align:left; padding:8px; border-bottom:2px solid #e5e7eb;">Voor</th>'
            : '';

        return <<<HTML
<table style="width:100%; border-collapse:collapse; margin:16px 0;">
    <thead>
        <tr>
            <th style="text-align:left; padding:8px; border-bottom:2px solid #e5e7eb;">Product</th>
            <th style="text-align:center; padding:8px; border-bottom:2px solid #e5e7eb;">Aantal</th>
            <th style="text-align:right; padding:8px; border-bottom:2px solid #e5e7eb;">Prijs</th>
            {$personHeader}
        </tr>
    </thead>
    <tbody>
        {$rows}
    </tbody>
</table>
HTML;
    }

    protected function renderAppointmentsByPerson(Order $order, ?int $filterPersonId = null): string
    {
        $items = $order->orderItems ?: collect();

        if (! $items instanceof Collection) {
            $items = collect($items);
        }

        if ($filterPersonId !== null) {
            $items = $items->where('person_id', $filterPersonId)->values();
        }

        if ($items->isEmpty()) {
            return '<p>Er zijn nog geen afspraken ingepland.</p>';
        }

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
        $person = $order->salesLead?->getContactPersonOrFirstPerson();

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
                ? ValueNormalizer::toString($variables[$key])
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

    private function formatPostalCodeForDisplay(string $postalCode): string
    {
        if (preg_match('/^([0-9]{4})([A-Z]{2})$/', $postalCode, $m)) {
            return $m[1].' '.$m[2];
        }

        return $postalCode;
    }
}
