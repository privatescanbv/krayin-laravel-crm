<?php

namespace App\Services\Afb;

use App\Models\Anamnesis;
use App\Models\Clinic;
use App\Models\ClinicDepartment;
use App\Models\Order;
use App\Models\PartnerProduct;
use App\Models\ResourceOrderItem;
use App\Services\Storage\DocumentStorage;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Webkul\Contact\Models\Person;

class AfbDocumentGenerator
{
    public function __construct(
        private readonly DocumentStorage $documentStorage
    ) {}

    /**
     * @return array{
     *     file_name: string,
     *     file_path: string,
     *     patient_name: ?string,
     *     person_id: ?int
     * }
     */
    public function generateForOrderAndClinic(Order $order, Clinic $clinic): array
    {
        $rendered = $this->renderHtmlForOrderAndClinic($order, $clinic);

        $html = $rendered['html'];
        $person = $rendered['person'];

        $pdfContent = Pdf::loadHTML($html)
            ->setPaper('A4', 'portrait')
            ->output();

        $datePart = now()->format('Ymd_His');
        $orderPart = $order->order_number ?: (string) $order->id;
        $fileName = sprintf(
            'afb_%s_%s_%s.pdf',
            Str::slug($clinic->name ?: 'clinic'),
            Str::slug($orderPart),
            $datePart
        );
        $filePath = sprintf('afb/%d/%d/%s', $clinic->id, $order->id, $fileName);

        $this->documentStorage->put($filePath, $pdfContent);

        return [
            'file_name'    => $fileName,
            'file_path'    => $filePath,
            'patient_name' => $person?->name,
            'person_id'    => $person?->id,
        ];
    }

    /**
     * @return array{
     *     file_name: string,
     *     file_path: string,
     *     patient_name: ?string,
     *     person_id: ?int
     * }
     */
    public function generateForOrderAndDepartment(Order $order, ClinicDepartment $department): array
    {
        $department->loadMissing('clinic');
        $rendered = $this->renderHtmlForOrderAndDepartment($order, $department);

        $html = $rendered['html'];
        $person = $rendered['person'];

        $pdfContent = Pdf::loadHTML($html)
            ->setPaper('A4', 'portrait')
            ->output();

        $datePart = now()->format('Ymd_His');
        $orderPart = $order->order_number ?: (string) $order->id;
        $fileName = sprintf(
            'afb_%s_%s_%s.pdf',
            Str::slug($department->clinic->name ?: 'clinic'),
            Str::slug($orderPart),
            $datePart
        );
        $filePath = sprintf('afb/%d/%d/%s', $department->clinic_id, $order->id, $fileName);

        $this->documentStorage->put($filePath, $pdfContent);

        return [
            'file_name'    => $fileName,
            'file_path'    => $filePath,
            'patient_name' => $person?->name,
            'person_id'    => $person?->id,
        ];
    }

    /**
     * @return array{html: string, person: ?Person}
     */
    public function renderHtmlForOrderAndClinic(Order $order, Clinic $clinic): array
    {
        $order->loadMissing([
            'user',
            'salesLead.user',
            'salesLead.contactPerson.address',
            'salesLead.persons.address',
            'orderItems.person.address',
            'orderItems.product.partnerProducts.clinics',
            'orderItems.resourceOrderItems.resource.clinic',
        ]);

        $examinations = $this->extractClinicExaminations($order, $clinic->id);
        $person = $this->resolvePerson($order, $examinations);
        $anamnesis = $this->resolveAnamnesis($order, $person);

        $html = view('adminc.afb.document', [
            'afb' => $this->buildViewData($order, $clinic, $person, $anamnesis, $examinations),
        ])->render();

        return [
            'html'   => $html,
            'person' => $person,
        ];
    }

    /**
     * @return array{html: string, person: ?Person}
     */
    public function renderHtmlForOrderAndDepartment(Order $order, ClinicDepartment $department): array
    {
        $department->loadMissing('clinic');

        $order->loadMissing([
            'user',
            'salesLead.user',
            'salesLead.contactPerson.address',
            'salesLead.persons.address',
            'orderItems.person.address',
            'orderItems.product.partnerProducts.clinics',
            'orderItems.resourceOrderItems.resource.clinicDepartment',
        ]);

        $examinations = $this->extractDepartmentExaminations($order, $department->id);
        $person = $this->resolvePerson($order, $examinations);
        $anamnesis = $this->resolveAnamnesis($order, $person);

        $html = view('adminc.afb.document', [
            'afb' => $this->buildViewData($order, $department->clinic, $person, $anamnesis, $examinations),
        ])->render();

        return [
            'html'   => $html,
            'person' => $person,
        ];
    }

    /**
     * @param  Collection<int, array{
     *     start_at: Carbon,
     *     date: string,
     *     appointment_time: string,
     *     start_time: string,
     *     clinic_description: string
     * }>  $examinations
     */
    private function buildViewData(
        Order $order,
        Clinic $clinic,
        ?Person $person,
        ?Anamnesis $anamnesis,
        Collection $examinations
    ): array {
        $address = $person?->address;

        return [
            'header' => [
                'clinic_name'     => $clinic->registration_form_clinic_name ?: $clinic->name ?: '-',
                'print_date'      => now()->format('d-m-Y'),
                'assigned_user'   => $order->user?->name ?: $order->salesLead?->user?->name ?: '-',
                'order_number'    => $order->order_number ?: (string) $order->id,
            ],
            'patient' => [
                'salutation'  => $person?->salutation?->label() ?: '-',
                'first_name'  => $person?->first_name ?: '-',
                'last_name'   => $this->formatLastName($person),
                'address'     => $this->formatAddressLine($address?->street, $address?->house_number, $address?->house_number_suffix),
                'postal_code' => $address?->postal_code ?: '-',
                'city'        => $address?->city ?: '-',
                'country'     => $address?->country ?: '-',
            ],
            'medical' => [
                'height'              => $anamnesis?->height,
                'weight'              => $anamnesis?->weight,
                'claustrophobia'      => $this->formatBoolean($anamnesis?->claustrophobia),
                'diabetes'            => $this->formatBoolean($anamnesis?->diabetes),
                'diabetes_notes'      => $this->emptyToNull($anamnesis?->diabetes_notes),
                'metals'              => $this->formatBoolean($anamnesis?->metals),
                'metals_notes'        => $this->emptyToNull($anamnesis?->metals_notes),
                'heart_surgery'       => $this->formatBoolean($anamnesis?->heart_surgery),
                'heart_surgery_notes' => $this->emptyToNull($anamnesis?->heart_surgery_notes),
                'implant'             => $this->formatBoolean($anamnesis?->implant),
                'implant_notes'       => $this->emptyToNull($anamnesis?->implant_notes),
                'allergies'           => $this->formatBoolean($anamnesis?->allergies),
                'allergies_notes'     => $this->emptyToNull($anamnesis?->allergies_notes),
                'contra_indication'   => $this->formatBoolean($anamnesis?->glaucoma),
                'contra_notes'        => $this->emptyToNull($anamnesis?->glaucoma_notes),
                'remark'              => $this->emptyToNull($anamnesis?->remarks),
            ],
            'examinations'     => $examinations->values()->all(),
            'clinic_anamnesis' => $this->emptyToNull($anamnesis?->comment_clinic),
            'extra_info'       => $this->emptyToNull($order->salesLead?->description),
        ];
    }

    /**
     * @return Collection<int, array{
     *     start_at: Carbon,
     *     date: string,
     *     appointment_time: string,
     *     start_time: string,
     *     clinic_description: string,
     *     order_item_person_id: ?int
     * }>
     */
    private function extractClinicExaminations(Order $order, int $clinicId): Collection
    {
        return $order->orderItems
            ->flatMap(function ($item) use ($clinicId) {
                return $item->resourceOrderItems
                    ->filter(fn (ResourceOrderItem $resourceOrderItem) => (int) $resourceOrderItem->resource?->clinic_id === $clinicId)
                    ->map(function (ResourceOrderItem $resourceOrderItem) use ($item, $clinicId) {
                        $start = $resourceOrderItem->from
                            ? Carbon::parse($resourceOrderItem->from)
                            : Carbon::parse($item->order?->first_examination_at ?? now());

                        return [
                            'start_at'              => $start,
                            'date'                  => $start->format('d-m-Y'),
                            'appointment_time'      => $item->order?->first_examination_at?->format('H:i') ?: $start->format('H:i'),
                            'start_time'            => $start->format('H:i'),
                            'clinic_description'    => $this->resolveClinicDescription($item->product?->partnerProducts ?? collect(), $clinicId)
                                ?: ($item->getProductDescription() ?: $item->getProductName() ?: '-'),
                            'order_item_person_id'  => $item->person_id ? (int) $item->person_id : null,
                        ];
                    });
            })
            ->sortBy(fn (array $row) => $row['start_at'])
            ->values();
    }

    /**
     * @return Collection<int, array{
     *     start_at: Carbon,
     *     date: string,
     *     appointment_time: string,
     *     start_time: string,
     *     clinic_description: string,
     *     order_item_person_id: ?int
     * }>
     */
    private function extractDepartmentExaminations(Order $order, int $departmentId): Collection
    {
        return $order->orderItems
            ->flatMap(function ($item) use ($departmentId) {
                return $item->resourceOrderItems
                    ->filter(fn (ResourceOrderItem $resourceOrderItem) => (int) $resourceOrderItem->resource?->clinic_department_id === $departmentId)
                    ->map(function (ResourceOrderItem $resourceOrderItem) use ($item) {
                        $start = $resourceOrderItem->from
                            ? Carbon::parse($resourceOrderItem->from)
                            : Carbon::parse($item->order?->first_examination_at ?? now());

                        $clinicId = $resourceOrderItem->resource?->clinicDepartment?->clinic_id;

                        return [
                            'start_at'              => $start,
                            'date'                  => $start->format('d-m-Y'),
                            'appointment_time'      => $item->order?->first_examination_at?->format('H:i') ?: $start->format('H:i'),
                            'start_time'            => $start->format('H:i'),
                            'clinic_description'    => ($clinicId ? $this->resolveClinicDescription($item->product?->partnerProducts ?? collect(), $clinicId) : null)
                                ?: ($item->getProductDescription() ?: $item->getProductName() ?: '-'),
                            'order_item_person_id'  => $item->person_id ? (int) $item->person_id : null,
                        ];
                    });
            })
            ->sortBy(fn (array $row) => $row['start_at'])
            ->values();
    }

    private function resolveClinicDescription(Collection $partnerProducts, int $clinicId): ?string
    {
        /** @var PartnerProduct|null $partnerProduct */
        $partnerProduct = $partnerProducts->first(function ($pp) use ($clinicId) {
            return $pp->clinics->contains('id', $clinicId);
        });

        return $this->emptyToNull($partnerProduct?->clinic_description);
    }

    private function resolvePerson(Order $order, Collection $examinations): ?Person
    {
        $examPersonId = $examinations
            ->pluck('order_item_person_id')
            ->filter()
            ->first();

        if ($examPersonId) {
            $person = $order->orderItems
                ->pluck('person')
                ->filter()
                ->firstWhere('id', (int) $examPersonId);

            if ($person) {
                return $person;
            }
        }

        if ($order->salesLead?->contactPerson) {
            return $order->salesLead->contactPerson;
        }

        return $order->salesLead?->persons?->first();
    }

    private function resolveAnamnesis(Order $order, ?Person $person): ?Anamnesis
    {
        if (! $person) {
            return null;
        }

        $leadId = $order->salesLead?->lead_id;

        return Anamnesis::query()
            ->where('person_id', $person->id)
            ->where(function ($query) use ($order, $leadId) {
                $query->where('sales_id', $order->sales_lead_id);

                if ($leadId) {
                    $query->orWhere('lead_id', $leadId);
                }
            })
            ->latest('updated_at')
            ->first();
    }

    private function formatLastName(?Person $person): string
    {
        if (! $person) {
            return '-';
        }

        $primary = trim(implode(' ', array_filter([
            $person->lastname_prefix,
            $person->last_name,
        ])));

        $married = trim(implode(' ', array_filter([
            $person->married_name_prefix,
            $person->married_name,
        ])));

        if ($primary && $married) {
            return $primary.' - '.$married;
        }

        return $primary ?: ($married ?: '-');
    }

    private function formatAddressLine(?string $street, mixed $houseNumber, ?string $suffix): string
    {
        $line = trim(implode(' ', array_filter([
            $street,
            trim((string) $houseNumber).($suffix ? ' '.$suffix : ''),
        ])));

        return $line !== '' ? $line : '-';
    }

    private function formatBoolean(?bool $value): string
    {
        if ($value === null) {
            return '-';
        }

        return $value ? 'Ja' : 'Nee';
    }

    private function emptyToNull(?string $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
