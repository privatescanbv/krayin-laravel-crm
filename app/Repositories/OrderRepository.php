<?php

namespace App\Repositories;

use App\Enums\AppointmentTimeFilter;
use App\Enums\OrderItemStatus;
use App\Models\Clinic;
use App\Models\ClinicDepartment;
use App\Models\Department;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ResourceOrderItem;
use App\Models\SalesLead;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Webkul\Contact\Models\Person;
use Webkul\Core\Eloquent\Repository;

class OrderRepository extends Repository
{
    protected $fieldSearchable = [
        'order_number',
        'title',
        'user_id',
        'user.name',
        'pipeline_stage_id',
    ];

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
    public function resolveEmailVariablesForOrder(int $orderId, ?int $personId = null): array
    {
        $order = $this->with([
            'orderItems.resourceOrderItems.resource.clinicDepartment.clinic.address',
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
            'order_reference'  => (string) $order->order_number,
            'order_number'     => $order->order_number ?? '',
            'order_title'      => $order->title ?? '',
            'order_status'     => $order->stage?->name ?? '',
            'order_total'      => $order->total_price ?? 0,
            'customer_name'    => $this->resolveCustomerName($order, $personId),
        ];

        $appointmentVariables = $this->resolveAppointmentVariables($order, $personId);
        $variables = array_merge($variables, $appointmentVariables);

        $variables['afspraken_tabel'] = $this->renderAppointmentsTable($order, $personId);

        return $variables;
    }

    /**
     * When sales is lost, mark non-won linked orders as lost on the order pipeline (Privatescan or Hernia).
     * Updates run per model so {@see \App\Observers\OrderObserver} can clear planning and set order lines to LOST.
     */
    public function cleanUpFromLostSales(string $salesId): void
    {
        $sales = SalesLead::with('lead.department')->find($salesId);

        if (! $sales) {
            return;
        }

        $lostOrderStageId = Order::lostOrderStageId($sales->lead?->department);
        $closedAt = $sales->closed_at ?? now();

        Order::query()
            ->where('sales_lead_id', $salesId)
            ->whereDoesntHave('stage', fn ($q) => $q->where('is_won', true))
            ->get()
            ->each(function (Order $order) use ($lostOrderStageId, $sales, $closedAt) {
                $order->update([
                    'pipeline_stage_id' => $lostOrderStageId,
                    'lost_reason'       => $sales->lost_reason,
                    'closed_at'         => $closedAt,
                ]);
            });
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
     * Base query for patient-portal orders (pipeline + person). Eager-loads relations needed for
     * {@see Order::firstExaminationCarbon()}. Does not apply future/past filter (use
     * {@see getPatientAppointmentOrdersForPerson()}).
     */
    public function queryPatientAppointmentsForPerson(Person $person): Builder
    {
        return Order::query()
            ->appointmentEligible()
            ->forPerson($person)
            ->with([
                'orderItems' => fn ($q) => $q->where('status', '!=', OrderItemStatus::LOST->value),
                'orderItems.resourceOrderItems',
            ]);
    }

    /**
     * Orders shown as patient appointments, filtered/sorted by resolved first-examination datetime.
     *
     * @return Collection<int, Order>
     */
    public function getPatientAppointmentOrdersForPerson(Person $person, ?AppointmentTimeFilter $filter = null, ?Carbon $now = null): Collection
    {
        $now = $now ?? now();

        return $this->queryPatientAppointmentsForPerson($person)
            ->get()
            ->filter(fn (Order $order) => $order->matchesAppointmentTimeFilter($filter, $now))
            ->sortBy(fn (Order $o) => $o->firstExaminationCarbon()?->getTimestamp() ?? PHP_INT_MAX)
            ->values();
    }

    /**
     * Paginate orders that are shown as patient appointments.
     */
    public function paginatePatientAppointmentsForPerson(Person $person, int $perPage, ?AppointmentTimeFilter $filter = null, ?Carbon $now = null): LengthAwarePaginator
    {
        $orders = $this->getPatientAppointmentOrdersForPerson($person, $filter, $now);
        $page = max(1, (int) request()->query('page', 1));

        return new LengthAwarePaginator(
            $orders->forPage($page, $perPage)->values(),
            $orders->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()],
        );
    }

    public function resolveClinicDepartmentForOrder(Order $order): ?ClinicDepartment
    {
        return $order->activeOrderItems()->first()?->resourceOrderItem()->with('resource')->first()?->resource->clinicDepartment ?? null;
    }

    /**
     * Assumption that clinics are equal over all order items
     *
     * @return Clinic|null, null if no planned order items
     */
    private function resolveClinicForOrder(Order $order): ?Clinic
    {
        return $order->activeOrderItems()->first()?->resourceOrderItem()->with('resource.clinicDepartment.clinic')->first()?->resource->clinicDepartment?->clinic ?? null;
    }

    /**
     * Resolve appointment-related variables from order.
     * Extracts the first appointment details for template variables.
     *
     * When $filterPersonId is set, uses the earliest booked slot among that person's order items only
     * (per-person mail). Otherwise uses {@see Order::firstExaminationCarbon()} (order-wide, includes overrides).
     */
    private function resolveAppointmentVariables(Order $order, ?int $filterPersonId = null): array
    {
        $variables = [
            'datum_afspraak'    => '',
            'tijd_afspraak'     => '',
            'plaats_afspraak'   => '',
            'datum_bevestiging' => '',
        ];

        $from = null;
        $clinic = null;

        if ($filterPersonId !== null) {
            $firstRoi = $this->earliestResourceOrderItemForPerson($order, $filterPersonId);
            if ($firstRoi?->from) {
                $from = Carbon::parse($firstRoi->from);
                $firstRoi->loadMissing('resource.clinicDepartment.clinic.address');
                $clinic = $firstRoi->resource?->clinicDepartment?->clinic;
            }
        } else {
            $resolved = $order->firstExaminationCarbon();
            if ($resolved) {
                $from = $resolved->copy();
                $clinic = $this->resolveClinicForOrder($order);
            }
        }

        if ($from) {
            $clinic?->loadMissing('address');
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
     * Earliest resource booking for a specific person on this order (non-lost items only).
     */
    private function earliestResourceOrderItemForPerson(Order $order, int $personId): ?ResourceOrderItem
    {
        $order->loadMissing('orderItems.resourceOrderItems.resource.clinicDepartment.clinic');

        $best = null;

        foreach ($order->displayableOrderItems($personId) as $orderItem) {

            foreach ($orderItem->resourceOrderItems as $resourceOrderItem) {
                if (! $resourceOrderItem->from) {
                    continue;
                }

                if ($best === null || Carbon::parse($resourceOrderItem->from)->lt(Carbon::parse($best->from))) {
                    $best = $resourceOrderItem;
                }
            }
        }

        return $best;
    }

    /**
     * Render appointments table using Blade template.
     */
    private function renderAppointmentsTable(Order $order, ?int $filterPersonId = null): string
    {
        $appointmentsByPerson = $this->prepareAppointmentsByPerson($order, $filterPersonId);

        return view('adminc.email_templates.order.order_items_appointments_table', [
            'appointmentsByPerson' => $appointmentsByPerson,
        ])->render();
    }

    /**
     * Prepare appointments data grouped by person.
     * When $filterPersonId is provided, only items for that person are included.
     */
    private function prepareAppointmentsByPerson(Order $order, ?int $filterPersonId = null): array
    {
        $appointmentsByPerson = [];

        foreach ($order->displayableOrderItems($filterPersonId) as $item) {

            $personId = $item->person_id ?? 'unknown';
            $personName = $item->person->name ?? 'Onbekend';

            if (! isset($appointmentsByPerson[$personId])) {
                $appointmentsByPerson[$personId] = [
                    'person_name'  => $personName,
                    'appointments' => [],
                ];
            }

            $resourceOrderItems = $item->resourceOrderItems;

            foreach ($resourceOrderItems as $resourceOrderItem) {
                $this->prepareAppointmentForResourceOrderItem($order, $appointmentsByPerson, $personId, $item, $resourceOrderItem);
            }
        }

        return $appointmentsByPerson;
    }

    private function prepareAppointmentForResourceOrderItem(
        Order $order,
        array &$appointmentsByPerson,
        $personId,
        OrderItem $item,
        ResourceOrderItem $resourceOrderItem
    ): void {
        if (! $resourceOrderItem->from) {
            return;
        }

        $resourceOrderItem->loadMissing('resource.clinicDepartment.clinic.address');

        $address = $resourceOrderItem->resource?->clinicDepartment?->clinic?->address;
        $appointmentsByPerson[$personId]['appointments'][] = [
            'product_name'        => $item->getProductName() ?? 'Onbekend product',
            'product_description' => $item->getProductDescription() ?? 'Onbekend product',
            'date'                => $this->formatDutchDate($resourceOrderItem->from),
            'time_from'           => Carbon::parse($resourceOrderItem->from)->format('H:i'),
            'time_to'             => $resourceOrderItem->to ? Carbon::parse($resourceOrderItem->to)->format('H:i') : null,
            'clinic_name'         => $resourceOrderItem->resource?->clinicDepartment?->clinic?->name ?? 'Onbekend',
            'address'             => $address ? $address->formatAddress() : '',
            'resource_name'       => $resourceOrderItem->resource?->name ?? 'Onbekend',
        ];
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
    private function resolveCustomerName(Order $order, ?int $personId = null): string
    {
        if ($personId) {
            $person = Person::find($personId);
            if ($person && $person->name) {
                return $person->name;
            }
        }

        $person = $order->salesLead?->getContactPersonOrFirstPerson();

        if ($person && $person->name) {
            return $person->name;
        }

        return 'heer/mevrouw';
    }
}
