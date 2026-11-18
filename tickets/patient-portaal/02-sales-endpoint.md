# 02 — Endpoint sales: lijst + detail

**Trello:** https://trello.com/c/mLDk8KHf
**Trello-label:** `NOT_READY`

**Afhankelijk van:** 01  ·  **Grootte:** M

## Doel

Het portaal kan alle sales van een patiënt ophalen (lijst met filter actief/afgerond) en één
sale in detail (kop + samenvatting).

## Endpoints

```
GET patient/{id}/sales?status=active|closed&page=&per_page=
GET patient/{id}/sales/{saleId}
```

- Lijst: nieuwste eerst (`created_at desc, id desc`), gepagineerd.
- Detail: 404 als het sale niet aan deze persoon gekoppeld is.

## Response (één sale)

```json
{
  "id": "123",
  "title": "Total Bodyscan Royal+",
  "department": { "id": "Privatescan", "label": "Privatescan" },
  "status":     { "id": "in_review", "label": "In behandeling bij Privatescan" },
  "is_closed": false,
  "started_at": "2026-09-02T10:00:00+02:00",
  "closed_at": null,
  "clinic":           { "id": "5", "label": "Evidia Essen" },
  "next_appointment": { "id": "order-77", "label": "12 dec 2026, Evidia Essen", "start_at": "2026-12-12T09:00:00+01:00" },
  "counts": { "documents": 6, "appointments": 2 }
}
```

- `clinic` en `next_appointment` zijn `null` zonder geplande afspraak in de toekomst.
- `next_appointment.id` volgt de appointments-API (`order-{id}`); adres en aanmeldtekst
  (`registration_note`) haalt het portaal daar op.
- Geen co-persons, interne fase, arts of beoordeling.

## Scope

- `routes/api/patient.php`: `sales` (index) en `sales/{saleId}` (show) in de groep
  `patient/{id}`, namen `api.patient.sales.index|show`.
- `app/Http/Controllers/Api/PatientSalesController.php`: patroon van `PatientAppointmentController`,
  Scribe-docblocks `@group Patient sales`.
- `app/Http/Requests/Api/PatientSalesIndexRequest.php`: uitbreiding van
  `PatientPaginatedIndexRequest` met `status` (`active|closed`).
- `SalesLead::scopeForPerson(Person)` op dezelfde manier als `Order::scopeForPerson`.
- `SalesLeadRepository`: `paginateSalesForPerson()` en `findSaleForPerson()`, met eager
  loading van `stage`, `department`, `lead.department` en orders (`appointmentEligible()`) met
  `orderItems.resourceOrderItems.resource.clinicDepartment.clinic`.
- `app/Http/Resources/PatientSaleResource.php` met een kleine `link(id, label)`-helper:
  - `title` = `SalesLead.name`. **Nagaan** of die naam patiëntwaardig is (geen interne prefixen); anders de
    titel afleiden van de producten van de eerste order.
  - `department` via `SalesLead::getDepartment()`.
  - `status` via `PatientSaleStatus::fromStage()`, plus de regel "in_review + geplande afspraak →
    scheduled" uit ticket 01.
  - `next_appointment`: toekomstige order met de vroegste `firstExaminationCarbon()`; kliniek via
    `OrderCheckService::retrieveClinicDepartmentFromOrder()`.
  - `counts.documents`: gepubliceerde documenten (`publishedToPortalForPerson`) met `sales_lead_id` van het
    sale of een `order_id` van een order in het sale. `counts.appointments`: orders die
    `appointmentEligible()` zijn.

## Acceptatie (`tests/Feature/Api/patient/PatientSalesTest.php`)

- Lijst toont alleen sales van deze persoon; `status=active|closed` filtert correct.
- `status` en `department` zijn `{id, label}`; minstens één Privatescan- en één Hernia-fase getest.
- `next_appointment` en `clinic` verwijzen naar de eerstvolgende geplande order, `null` zonder afspraak.
- `counts.documents` telt geen ongepubliceerde documenten.
- Detail van een sale van een ander geeft 404; bearer van een andere patiënt geeft 403
  (patroon `PatientDocumentsTest`).
- Aantal queries blijft constant bij meer sales (patroon `ClinicSalesLeadsTest`).
