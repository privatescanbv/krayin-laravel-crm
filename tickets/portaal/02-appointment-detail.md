# 02 — Afspraak-detail en stabiele afspraak-id's (gedeeld: kliniek- en patiëntportaal)

**Trello:** https://trello.com/c/jVCi9fPG
**Trello-label:** `NOT_READY`

**Afhankelijk van:** [01](01-document-type.md) (voor `preparation[]`)  ·  **Grootte:** M  ·  **Prio:** hoog  ·  **Bron:** APPT-001, APPT-002, K15, V5, gap-analyse G-4

**Status (2026-11-19):** gebouwd — V5 besluit (id = boeking), `AppointmentDetailResource` +
`AppointmentDetailService`, beide detailroutes, lijst-id/dode velden/N+1. Twee bewuste afwijkingen:
- De kliniekroute draait op `clinic/{keycloakUserId}/appointments/{id}` + `SalesLead::forPortalDoctor`
  (bestaande auth, alleen Arts), niet op `portal/appointments/{id}` + `scopeVisibleInPortalFor`: die auth-laag
  (G-1a, `keycloak.staff`-middleware, niet-Arts-rollen) bestaat nog niet. Resource/service zijn al gedeeld, dus
  verhuizen naar `portal/*` is straks alleen een routewijziging.
- `preparation[]` geeft altijd `[]`. Er bestaat nog geen koppeling document ↔ producttype (alleen document ↔
  order/persoon); dat is zelf een nieuw datamodel, niet iets wat ticket 01 al levert. Volgt als los ticket.

## Aanname (voorlopig)

Kliniek en patiënt mogen **dezelfde afspraakdetails** zien. Daarom één Resource voor beide portalen. Klopt dat
later niet (bijv. kliniek-only velden als kamer of OP-record, zie G-5), dan splitsen we de Resource of voegen we
een rol-specifiek blok toe; het afspraak-id en de basisvelden blijven gelijk. Geen prijzen of interne velden
(financieel, inkoop, interne notities) in deze Resource, voor niemand.

## Doel

Beide portalen kunnen één afspraak tonen (datum, aankomsttijd, locatie, duur, met wie, wat erbinnen valt,
voorbereiding). Het afspraak-id is stabiel en voor beide portalen hetzelfde, ook als een order meerdere
afspraken krijgt.

## Scope

- Besluit V5 vastleggen: afspraak-id = boeking (`ResourceOrderItem`) of order. Advies api-voorstel: boeking.
  Bij een wissel: het oude formaat `order-{id}` nog een tijd accepteren.
- Eén `AppointmentDetailResource` (en lijstvorm) die door beide portalen gebruikt wordt, met:
  - `id`, `type: {id, label}` (producttype, patiëntvriendelijke naam), `start_at`, `end_at`,
    `sale: {id, label}`;
  - `clinic: {id, label}` + adres, `registration_note` (al gebouwd in de lijst);
  - `items[]`: onderzoeken binnen de afspraak (label), zonder prijzen;
  - `preparation[]`: links naar documenten van het type "voorbereiding" voor dit producttype (alleen types die voor het betreffende portaal aanstaan)
    ([01](01-document-type.md)).
- Endpoints, elk met eigen autorisatie maar dezelfde Resource:
  - patiënt: `GET patient/{id}/appointments/{appointmentId}` (alleen eigen afspraken);
  - kliniek: `GET portal/appointments/{id}` (`SalesLead::scopeVisibleInPortalFor`, G-1a).
- De lijst `GET patient/{id}/appointments` krijgt het nieuwe id; dode velden (`practitioner_id`, `end_at` altijd
  null, `is_remote`) worden gevuld of weggehaald.
- N+1 in `PatientPortalAppointmentResource` (`Clinic::find` per afspraak) oplossen.

## Acceptatie

- Patiënt: detail van een afspraak van een ander geeft 404.
- Kliniek: detail van een afspraak buiten de eigen scope geeft 404 (zodra G-1a er is).
- Geen prijzen of interne velden in de response.
- Aantal queries in de lijst blijft constant.
