<?php

namespace App\Services\Mail;

use App\Enums\EmailTemplateCode;
use App\Helpers\ValueNormalizer;
use App\Models\Anamnesis;
use App\Models\Order;
use App\Models\SalesLead;
use App\Repositories\OrderRepository;
use App\Repositories\SalesLeadRepository;
use Exception;
use Illuminate\Support\Facades\View;
use ReflectionClass;
use ReflectionException;
use Webkul\Contact\Repositories\PersonRepository;
use Webkul\EmailTemplate\Models\EmailTemplate;
use Webkul\Lead\Repositories\LeadRepository;

class EmailTemplateRenderingService
{
    /**
     * Beschikbare template variabelen per entity type.
     *
     * Dit is de single source of truth voor documentatie en wordt
     * gebruikt in docs/modules/application/pages/email.adoc.
     *
     * Structuur: entity => [ variabele => omschrijving ]
     */
    public const SUPPORTED_VARIABLES = [
        'user' => [
            'user.first_name' => 'Voornaam van de gebruiker',
            'user.last_name'  => 'Achternaam van de gebruiker',
            'user.email'      => 'E-mailadres van de gebruiker',
            'user.*'          => 'Nested toegang tot alle User attributen',
        ],
        'lead' => [
            'lastname'   => 'Achternaam: contactpersoon > eerste gekoppelde persoon > lead zelf',
            'lead.*'     => 'Nested toegang tot alle Lead attributen, bijv. {{ lead.name }}',
        ],
        'person' => [
            'lastname'       => 'Achternaam van de persoon',
            'gvl_form_link'  => 'Link naar het GVL formulier (indien anamnesis beschikbaar)',
            'gvl_deadline'   => 'Deadline GVL formulier (1 week vanaf nu, dd-mm-jjjj)',
            'person.*'       => 'Nested toegang tot alle Person attributen, bijv. {{ person.last_name }}',
        ],
        'sales_lead' => [
            'lastname'       => 'Achternaam: contactpersoon > eerste gekoppelde persoon',
            'sales_lead.*'   => 'Nested toegang tot alle SalesLead attributen',
        ],
        'order' => [
            'order_reference'    => 'Order ID als referentienummer',
            'order_title'        => 'Titel van de order',
            'order_status'       => 'Status van de order (leesbaar label)',
            'order_total'        => 'Totaalbedrag van de order',
            'customer_name'      => 'Klantnaam (via contactpersoon of lead)',
            'datum_afspraak'     => 'Datum eerste afspraak (bijv. "15 januari 2025")',
            'tijd_afspraak'      => 'Tijdstip eerste afspraak (bijv. "14:00")',
            'plaats_afspraak'    => 'Locatie eerste afspraak (kliniek + adres)',
            'datum_bevestiging'  => 'Bevestigingsdeadline (3 dagen voor afspraak)',
            'afspraken_tabel'    => 'HTML tabel met alle afspraken gegroepeerd per persoon',
            'order_items_table'  => 'HTML tabel met alle orderregels',
            'order.*'            => 'Nested toegang tot alle Order attributen, bijv. {{ order.id }}',
        ],
    ];

    public function __construct(
        private readonly EmailRenderingService $emailRenderingService,
        private readonly LeadRepository $leadRepository,
        private readonly PersonRepository $personRepository,
        private readonly SalesLeadRepository $salesLeadRepository,
        private readonly OrderRepository $orderRepository,
    ) {}

    /**
     * Render an email template by code with entity-based variable resolution.
     *
     * @param  array<string, mixed>  $entities  Entity map, e.g. ['user' => $user, 'lead' => 123, 'person' => $person]
     * @return array{subject: string, html: string}
     */
    public function renderForEntities(EmailTemplateCode $code, array $entities): array
    {
        $template = EmailTemplate::byCodeEnum($code)->firstOrFail();
        $variables = $this->resolveVariablesFromEntities($entities);

        return $this->render($template, $variables);
    }

    /**
     * Render an EmailTemplate from DB into final HTML (with standard layout + inline CSS).
     *
     * @return array{subject: string, html: string}
     */
    public function render(EmailTemplate $template, array $variables = []): array
    {
        $subject = $this->interpolateTemplate($template->subject, $variables);
        $interpolatedContent = $this->interpolateTemplate($template->content, $variables);

        // Re-use the standard mail wrapper used by admin mail templates.
        $templateWithInterpolatedContent = clone $template;
        $templateWithInterpolatedContent->content = $interpolatedContent;

        $htmlContent = View::make('adminc.emails.mail-template', [
            'template' => $templateWithInterpolatedContent,
        ])->render();

        return [
            'subject' => $subject,
            'html'    => $this->emailRenderingService->rendInlineCss($htmlContent),
        ];
    }

    /**
     * Resolve variables from an entities map.
     *
     * Accepts model objects or integer IDs. Models are used directly;
     * IDs are loaded via the corresponding repository.
     *
     * @param  array<string, mixed>  $entities
     * @return array<string, mixed>
     */
    public function resolveVariablesFromEntities(array $entities): array
    {
        $variables = [];
        $leadId = $entities['lead'] ?? null;
        $personId = $entities['person'] ?? null;
        $salesLeadId = $entities['sales_lead'] ?? null;
        $orderId = $entities['order'] ?? null;

        // Extract IDs from model objects
        if (is_object($leadId)) {
            $variables['lead'] = $leadId;
            $leadId = $leadId->id;
        }
        if (is_object($personId)) {
            $variables['person'] = $personId;
            $personId = $personId->id;
        }
        if (is_object($salesLeadId)) {
            $variables['sales_lead'] = $salesLeadId;
            $salesLeadId = $salesLeadId->id;
        }
        if (is_object($orderId)) {
            $variables['order'] = $orderId;
            $orderId = $orderId->id;
        }

        // User entity (model only, no repository lookup – use nested access: {{ user.first_name }})
        if (isset($entities['user'])) {
            $user = $entities['user'];
            if (is_object($user)) {
                $variables['user'] = $user;
            }
        }

        // Resolve order variables (highest priority for order templates)
        if ($orderId) {
            $orderVariables = $this->orderRepository->resolveEmailVariablesForOrder($orderId);

            if (! empty($orderVariables)) {
                $variables = array_merge($variables, $orderVariables);

                // Render order items table (needs order object)
                if (isset($variables['order'])) {
                    $variables['order_items_table'] = $this->renderOrderItemsTable($variables['order']);
                }

                // If order has sales lead, also resolve sales lead variables
                if (isset($variables['order']) && $variables['order']->salesLead) {
                    $salesLeadId = $variables['order']->salesLead->id;
                    if ($variables['order']->salesLead->lead) {
                        $leadId = $variables['order']->salesLead->lead->id;
                    }
                }
            }
        }

        // Resolve lead variables
        if ($leadId) {
            $leadVariables = $this->leadRepository->resolveEmailVariablesById($leadId);
            $variables = array_merge($variables, $leadVariables);

            if (! isset($variables['lead'])) {
                $lead = $this->leadRepository->find($leadId);
                if ($lead) {
                    $variables['lead'] = $lead;
                }
            }
        }

        // Resolve person variables
        if ($personId) {
            $personVariables = $this->personRepository->resolveEmailVariablesById($personId);
            $variables = array_merge($variables, $personVariables);

            if (! isset($variables['person'])) {
                $person = $this->personRepository->find($personId);
                if ($person) {
                    $variables['person'] = $person;
                }
            }
        }

        // Resolve sales lead variables
        if ($salesLeadId) {
            $salesVariables = $this->salesLeadRepository->resolveEmailVariablesById($salesLeadId);
            $variables = array_merge($variables, $salesVariables);

            if (! isset($variables['sales_lead'])) {
                $salesLead = SalesLead::with(['lead', 'orders'])->find($salesLeadId);
                if ($salesLead) {
                    $variables['sales_lead'] = $salesLead;
                    if ($salesLead->lead) {
                        $variables['lead'] = $salesLead->lead;
                    }
                    if (! isset($variables['order'])) {
                        $order = $salesLead->orders()->latest()->first();
                        if ($order) {
                            $variables['order'] = $order;
                        }
                    }
                }
            }
        }

        // Handle GVL form link
        if ($personId) {
            $gvlFormLink = $this->resolveGvlFormLink($personId, $leadId);
            if ($gvlFormLink) {
                $variables['gvl_form_link'] = $gvlFormLink;
                $variables['gvl_deadline'] = now()->addWeek()->format('d-m-Y');
            }
        }

        return $variables;
    }

    /**
     * Interpolate template content with variables.
     * Supports both {{ variable }} and {% variable %} syntax.
     * Supports nested properties like {%lead.name%} or {{order.id}}.
     */
    public function interpolateTemplate(string $template, array $variables): string
    {
        $resolve = function (string $key) use ($variables): string {
            $key = trim($key);

            // Allow {{ $lastname }} style.
            if (str_starts_with($key, '$')) {
                $key = ltrim($key, '$');
            }

            // Nested access (e.g. lead.name) - best-effort.
            if (str_contains($key, '.')) {
                $parts = explode('.', $key, 2);
                $objectKey = $parts[0];
                $propertyKey = $parts[1];

                if (array_key_exists($objectKey, $variables)) {
                    $object = $variables[$objectKey];

                    if (is_object($object)) {
                        if (method_exists($object, 'getAttribute')) {
                            try {
                                $value = $object->getAttribute($propertyKey);
                                if ($value !== null) {
                                    return $this->convertValueToString($value);
                                }
                            } catch (Exception $e) {
                                // Continue to next method
                            }
                        }
                        if (property_exists($object, $propertyKey)) {
                            return $this->convertValueToString($object->$propertyKey);
                        }
                    } elseif (is_array($object) && array_key_exists($propertyKey, $object)) {
                        return $this->convertValueToString($object[$propertyKey]);
                    }
                }

                return '';
            }

            if (! array_key_exists($key, $variables)) {
                return '';
            }

            $value = $variables[$key];

            return $value === null ? '' : $this->convertValueToString($value);
        };

        $template = preg_replace_callback('/\{\{\s*(.*?)\s*\}\}/', function ($matches) use ($resolve) {
            return $resolve($matches[1] ?? '');
        }, $template) ?? $template;

        $template = preg_replace_callback('/\{\%\s*(.*?)\s*\%\}/', function ($matches) use ($resolve) {
            return $resolve($matches[1] ?? '');
        }, $template) ?? $template;

        return $template;
    }

    /**
     * Render a template to full HTML with inline CSS.
     */
    public function renderTemplateToHTML(EmailTemplate $template, array $variables): string
    {
        $interpolatedContent = $this->interpolateTemplate($template->content, $variables);

        $templateWithInterpolatedContent = clone $template;
        $templateWithInterpolatedContent->content = $interpolatedContent;

        $htmlContent = View::make('adminc.emails.mail-template', [
            'template' => $templateWithInterpolatedContent,
        ])->render();

        return $this->emailRenderingService->rendInlineCss($htmlContent);
    }

    /**
     * Convert a value to string, handling enums properly.
     */
    private function convertValueToString($value): string
    {
        if (is_object($value)) {
            try {
                $reflection = new ReflectionClass($value);
                if ($reflection->isEnum()) {
                    if (method_exists($value, 'label')) {
                        return $value->label();
                    }
                    if ($reflection->hasProperty('value')) {
                        return (string) $value->value;
                    }
                    if (method_exists($value, 'name')) {
                        return $value->name;
                    }
                }
            } catch (ReflectionException $e) {
                // Not an enum, continue to normal conversion
            }
        }

        return ValueNormalizer::toString($value);
    }

    /**
     * Render order items table HTML using Blade template.
     */
    private function renderOrderItemsTable(Order $order): string
    {
        return view('adminc.email_templates.order.order_items_table', [
            'order' => $order,
        ])->render();
    }

    /**
     * Resolve GVL form link for a person.
     */
    private function resolveGvlFormLink(int $personId, ?int $leadId = null): ?string
    {
        $anamnesis = null;

        if ($leadId) {
            $anamnesis = Anamnesis::where('lead_id', $leadId)
                ->where('person_id', $personId)
                ->first();
        }

        if (! $anamnesis) {
            $anamnesis = Anamnesis::where('person_id', $personId)
                ->whereNotNull('gvl_form_link')
                ->where('gvl_form_link', '!=', '')
                ->latest('updated_at')
                ->first();
        }

        if ($anamnesis && ! empty($anamnesis->gvl_form_link)) {
            return $anamnesis->gvl_form_link;
        }

        return null;
    }
}
