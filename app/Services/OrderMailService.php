<?php

namespace App\Services;

use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Collection;
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

        $persons = Collection::make();

        if ($order->salesLead->contactPerson) {
            $persons->push($order->salesLead->contactPerson);
        }

        if ($order->salesLead->persons) {
            foreach ($order->salesLead->persons as $person) {
                $persons->push($person);
            }
        }

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

        if ($order->salesLead->persons) {
            foreach ($order->salesLead->persons as $person) {
                foreach ($person->emails ?? [] as $email) {
                    if (! empty($email['value'])) {
                        return $email['value'];
                    }
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
        return <<<HTML
<p>Beste {{ customer_name }},</p>
<p>Hierbij ontvangt u de samenvatting van order {{ order_reference }} ({{ order_title }}).</p>
{{ order_summary_table }}
<p><strong>Totaalbedrag:</strong> {{ order_total }}</p>
<p>{{ approval_instructions }}</p>
<p>Met vriendelijke groet,<br>{{ company_signature }}</p>
HTML;
    }

    protected function buildTemplateVariables(Order $order): array
    {
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
        $person = $order->salesLead?->getContactPersonOrFirstPerson();

        if ($person && $person->name) {
            return $person->name;
        }

        return 'klant';
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
}

