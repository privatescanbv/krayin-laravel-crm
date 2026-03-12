<?php

namespace App\Repositories;

use App\Enums\AppointmentTimeFilter;
use App\Models\Department;
use App\Models\Order;
use App\Models\SalesLead;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Webkul\Contact\Models\Person;
use Webkul\Core\Eloquent\Repository;

class OrderRepository extends Repository
{
    public function model(): string
    {
        return Order::class;
    }

    public function createFromSalesLead(int $salesLeadId, string $salesLeadName, ?Department $departmentName = null): Order
    {
        $salesLead = SalesLead::find($salesLeadId);

        return $this->create(
            ['title'                => $salesLeadName,
                'total_price'       => 0.00,
                'pipeline_stage_id' => Order::firstOrderStageId($departmentName),
                'sales_lead_id'     => $salesLeadId,
                'user_id'           => $salesLead?->user_id]
        );
    }

    /**
     * Resolve email template variables from order.
     * Returns an array of variables that can be used in email templates.
     */
    public function resolveEmailVariablesForOrder(int $orderId): array
    {
        $order = $this->with([
            'orderItems.resourceOrderItems.resource.clinic.address',
            'orderItems.resourceOrderItems.resource.resourceType',
            'orderItems.person',
            'orderItems.product',
            'salesLead.lead',
        ])->find($orderId);

        if (! $order) {
            return [];
        }

        $variables = [
            'order'            => $order,
            'order_reference'  => (string) $order->id,
            'order_number'     => $order->order_number ?? '',
            'order_title'      => $order->title ?? '',
            'order_status'     => $order->stage?->name ?? '',
            'order_total'      => $order->total_price ?? 0,
            'customer_name'    => $this->resolveCustomerName($order),
        ];

        // Resolve appointment variables from resource order items
        $appointmentVariables = $this->resolveAppointmentVariables($order);
        $variables = array_merge($variables, $appointmentVariables);

        // Render appointments table
        $variables['afspraken_tabel'] = $this->renderAppointmentsTable($order);

        return $variables;
    }

    /**
     * Clean up unapproved orders, when sales is lost.
     * This way we clean data and frees the agenda for clinics to plan other persons.
     */
    public function cleanUpFromLostSales(string $salesId): void
    {
        Order::where('sales_lead_id', $salesId)
            ->whereDoesntHave('stage', fn ($q) => $q->where('is_won', true))
            ->delete();
    }

    /**
     * Get all order ids for a given patient (Person).
     *
     * @return array<int>
     */
    public function getIdsForPerson(Person $person): array
    {
        return Order::query()
            ->forPerson($person)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Get a query builder for orders shown as patient appointments.
     */
    public function queryPatientAppointmentsForPerson(Person $person, ?AppointmentTimeFilter $filter = null, ?Carbon $now = null): Builder
    {
        $now = $now ?: now();

        return Order::query()
            ->appointmentEligible()
            ->forPerson($person)
            ->appointmentTimeFilter($filter, $now)
            ->orderBy('first_examination_at', 'asc');
    }

    /**
     * Paginate orders that are shown as patient appointments.
     */
    public function paginatePatientAppointmentsForPerson(Person $person, int $perPage, ?AppointmentTimeFilter $filter = null, ?Carbon $now = null): LengthAwarePaginator
    {
        return $this->queryPatientAppointmentsForPerson($person, $filter, $now)->paginate($perPage);
    }

    /**
     * Resolve appointment-related variables from order.
     * Extracts the first appointment details for template variables.
     */
    private function resolveAppointmentVariables(Order $order): array
    {
        $variables = [
            'datum_afspraak'    => '',
            'tijd_afspraak'     => '',
            'plaats_afspraak'   => '',
            'datum_bevestiging' => '',
        ];

        // Find the first resource order item (appointment) across all order items
        $firstAppointment = null;
        foreach ($order->orderItems ?? [] as $orderItem) {
            foreach ($orderItem->resourceOrderItems ?? [] as $resourceOrderItem) {
                if ($resourceOrderItem->from) {
                    if (! $firstAppointment || $resourceOrderItem->from < $firstAppointment->from) {
                        $firstAppointment = $resourceOrderItem;
                    }
                }
            }
        }

        if ($firstAppointment && $firstAppointment->from) {
            $from = Carbon::parse($firstAppointment->from);
            $clinic = $firstAppointment->resource?->clinic;
            $address = $clinic?->address;

            // Format date in Dutch (e.g., "15 januari 2025")
            $monthNames = [
                1 => 'januari', 2 => 'februari', 3 => 'maart', 4 => 'april',
                5 => 'mei', 6 => 'juni', 7 => 'juli', 8 => 'augustus',
                9 => 'september', 10 => 'oktober', 11 => 'november', 12 => 'december',
            ];
            $monthName = $monthNames[$from->month] ?? $from->format('F');
            $variables['datum_afspraak'] = $from->format('d').' '.$monthName.' '.$from->format('Y');

            // Format time (e.g., "14:00")
            $variables['tijd_afspraak'] = $from->format('H:i');

            // Format location (clinic name + address)
            $locationParts = [];
            if ($clinic && $clinic->name) {
                $locationParts[] = $clinic->name;
            }
            if ($address) {
                $addressParts = array_filter([
                    $address->street ?? null,
                    $address->city ?? null,
                ]);
                if (! empty($addressParts)) {
                    $locationParts[] = implode(', ', $addressParts);
                }
            }
            $variables['plaats_afspraak'] = ! empty($locationParts) ? implode(', ', $locationParts) : '';

            // Calculate confirmation deadline (3 days before appointment)
            $confirmationDeadline = $from->copy()->subDays(3);
            $deadlineMonthName = $monthNames[$confirmationDeadline->month] ?? $confirmationDeadline->format('F');
            $variables['datum_bevestiging'] = $confirmationDeadline->format('d').' '.$deadlineMonthName.' '.$confirmationDeadline->format('Y');
        }

        return $variables;
    }

    /**
     * Render appointments table using Blade template.
     */
    private function renderAppointmentsTable(Order $order): string
    {
        // Prepare appointments data grouped by person
        $appointmentsByPerson = $this->prepareAppointmentsByPerson($order);

        return view('adminc.email_templates.order.order_items_appointments_table', [
            'appointmentsByPerson' => $appointmentsByPerson,
        ])->render();
    }

    /**
     * Prepare appointments data grouped by person.
     */
    private function prepareAppointmentsByPerson(Order $order): array
    {
        $appointmentsByPerson = [];
        $items = $order->orderItems ?? collect();

        foreach ($items as $item) {
            $personId = $item->person_id ?? 'unknown';
            $personName = $item->person->name ?? 'Onbekend';

            if (! isset($appointmentsByPerson[$personId])) {
                $appointmentsByPerson[$personId] = [
                    'person_name'  => $personName,
                    'appointments' => [],
                ];
            }

            $resourceOrderItems = $item->resourceOrderItems ?? collect();

            foreach ($resourceOrderItems as $resourceOrderItem) {
                if (! $resourceOrderItem->from) {
                    continue;
                }

                // Ensure resource is loaded (should be eager loaded, but check anyway)
                if (! $resourceOrderItem->relationLoaded('resource')) {
                    $resourceOrderItem->load('resource.clinic.address');
                }

                $address = $resourceOrderItem->resource?->clinic?->address;

                $appointmentsByPerson[$personId]['appointments'][] = [
                    'product_name'        => $item->getProductName() ?? 'Onbekend product',
                    'product_description' => $item->getProductDescription() ?? 'Onbekend product',
                    'date'                => $this->formatDutchDate($resourceOrderItem->from),
                    'time_from'           => Carbon::parse($resourceOrderItem->from)->format('H:i'),
                    'time_to'             => $resourceOrderItem->to ? Carbon::parse($resourceOrderItem->to)->format('H:i') : null,
                    'clinic_name'         => $resourceOrderItem->resource?->clinic?->name ?? 'Onbekend',
                    'address'             => $address ? $address->formatAddress() : '',
                    'resource_name'       => $resourceOrderItem->resource?->name ?? 'Onbekend',
                ];
            }
        }

        return $appointmentsByPerson;
    }

    /**
     * Format date in Dutch format (e.g., "15 januari 2025").
     */
    private function formatDutchDate($date): string
    {
        $monthNames = [
            1 => 'januari', 2 => 'februari', 3 => 'maart', 4 => 'april',
            5 => 'mei', 6 => 'juni', 7 => 'juli', 8 => 'augustus',
            9 => 'september', 10 => 'oktober', 11 => 'november', 12 => 'december',
        ];

        $carbon = Carbon::parse($date);
        $monthName = $monthNames[$carbon->month] ?? $carbon->format('F');

        return $carbon->format('d').' '.$monthName.' '.$carbon->format('Y');
    }

    /**
     * Resolve customer name from order.
     */
    private function resolveCustomerName(Order $order): string
    {
        $person = $order->salesLead?->contactPerson;
        if (! $person) {
            // Fallback to the first person attached to the lead, if available
            $person = $order->salesLead?->lead?->persons()?->first();
        }

        if ($person && $person->name) {
            return $person->name;
        }

        return 'heer/mevrouw';
    }
}
