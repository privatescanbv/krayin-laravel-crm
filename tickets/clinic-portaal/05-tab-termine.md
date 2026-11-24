# 05 — Tab Termine: afspraken per patiënt van een sale

- **Status:** gedaan (2026-11-24)
  - Repo/branch: krayin-laravel-crm `development` (commits `7ebc20066`, `97fc22e6d`); clinic-portal `development`, ongecommit in de werkmap.
  - Gedaan (CRM): `GET clinic/{keycloakUserId}/sales-leads/{saleId}/appointments/{personId}` (`ClinicSalesLeadAppointmentController`,
    `ClinicSalesAppointmentResource`, `AppointmentDetailService::forPerson()`); `persons[].counts` (`scheduled`, `unscheduled`) in de
    sale-detail; Bearer-whitelist in `ApiKeyAuth` (ook het afspraak-detail); `order_items.person_id` verplicht op databaseniveau (zie onderaan).
  - Gedaan (portaal): `ClinicCrmClient::fetchPersonAppointments()`, `SalesController::detail()` haalt per persoon de regels op,
    `sales/_detail.blade.php` toont ze per patiënt; in de browser gecontroleerd op een sale met twee patiënten.
  - Afwijking van de eerste versie: de lijst staat per patiënt, niet per sale; `appointments[]` en `counts.appointments` zijn uit de sale-detail gehaald.

**Afhankelijk van:** [portaal/02](../portaal/02-appointment-detail.md)  ·  **Grootte:** M  ·  **Bron:** [gap-analyse](00-gap-analyse.md) §3.2

## Doel

De arts opent de kaart van een patiënt en ziet daar **alle orderitems van die patiënt uit alle orders van de sale**: wat is
ingepland (met tijd en kliniek), wat nog niet is ingepland en wat geen afspraak nodig heeft. De afspraken van een andere patiënt
van dezelfde sale staan er nooit tussen. Los van het patiëntportaal, daar gelden andere eisen.

## Scope

- `GET clinic/{keycloakUserId}/sales-leads/{saleId}/appointments/{personId}`; `personId` is `persons[].id` van de sale.
  Sale via `forPortalDoctor()`, de persoon moet aan de sale gekoppeld zijn, anders 404.
- Eén regel per boeking van een niet-verloren orderitem (`person_id` = de patiënt). Velden: `id` (boeking, voor het afspraak-detail; null
  zonder boeking), `order_item_id`, `order_id`, `label`, `type`, `status`, `start_at`, `end_at`, `expected_date`, `clinic`. Geen prijzen.
- `status`: `scheduled` (boeking met tijd), `unscheduled` (plannbaar, nog geen boeking; `expected_date` = datum van de order als die er is),
  `not_applicable` (product hoeft niet gepland te worden).
- Volgorde: komend (oplopend), verleden (nieuwste eerst), niet ingepland, geen afspraak nodig. `meta.counts` per status.
- Verloren/foutieve orderitems en items zonder persoon worden nooit getoond.
- `persons[].counts` in de sale-detail: aantal ingepland en nog niet ingepland per patiënt, zonder extra aanroep.
- Bearer-whitelist in `ApiKeyAuth` uitbreiden.

## Acceptatie

- Sale met twee patiënten: elke patiënt ziet alleen zijn eigen orderitems, ook als één order items van beide heeft.
- Items uit alle orders van de sale staan erbij; een item met meerdere boekingen geeft meerdere regels.
- Niet ingeplande items zijn zichtbaar als "Nog niet ingepland", met verwachte datum als die bekend is.
- Persoon die niet bij de sale hoort, of sale van een andere arts: 404.
- Geen prijzen of interne velden in de response.

## Orderitems zonder persoon (opgelost, 2026-11-24)

`order_items.person_id` is nu `NOT NULL` met FK `RESTRICT` (migratie `2026_11_24_000000_make_order_items_person_id_required`).
Het orderformulier eiste de persoon al (`OrderRequest`); de 19 lege rijen lokaal kwamen uit een import van 2026-08-05. De migratie
vult ze op: de persoon van de sale (bij meerdere de eerste), anders de eerste persoon van de lead, en breekt af als er daarna nog
lege rijen zijn. Gokken (sale met meerdere personen of via de lead) staan als warning in `storage/logs` ("order_items.person_id
guessed"). Productie kan andere rijen hebben; controleer de log na het deployen.
