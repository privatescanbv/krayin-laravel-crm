# 03 — `sale_id`-filter op afspraken en documenten

**Trello:** https://trello.com/c/kHC5QtA9
**Trello-label:** `NOT_READY`

**Afhankelijk van:** –  ·  **Grootte:** S

## Doel

Het sale-detail haalt zijn afspraken en documenten op via de bestaande endpoints, in plaats van
geneste kopieën in de sale-response (afspraak 2 in [00-plan](00-plan.md)).

## Scope

- `GET patient/{id}/appointments?sale_id=`
  - `PatientAppointmentsIndexRequest`: `sale_id` (nullable, integer).
  - `OrderRepository::queryPatientAppointmentsForPerson()`: `->where('sales_lead_id', $saleId)`.
- `GET patient/{id}/documents?sale_id=`
  - `PatientDocumentsIndexRequest`: `sale_id` (nullable, integer).
  - `ActivityRepository::paginateDocumentFilesForPerson()`: activity met `sales_lead_id` = sale, of
    `order_id` in de orders van dat sale.
- Elke afspraak en elk document krijgt `sale: { id, label }` in de response, zodat het portaal
  ("Naar dit sale") terug kan linken. `label` = titel van het sale.
- Scribe `queryParameters()` bijwerken.

## Acceptatie

- Per endpoint één test: filter geeft alleen items van dat sale.
- Een `sale_id` van een sale van een andere persoon geeft een lege lijst (geen lek).
- Bestaande tests in `PatientAppointmentsApiTest` en `PatientDocumentsTest` blijven groen.
