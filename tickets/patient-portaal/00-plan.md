# Plan: Patiëntportaal — CRM API-laag

## Story

> Als patiënt wil ik in het portaal mijn zorg bij Privatescan en Herniapoli zien (sales, afspraken,
> documenten, berichten, acties), zodat ik weet waar ik sta en wat ik nog moet doen.

**Scope van deze map: alleen CRM-werk** (API, datamodel, CRM-admin). Het patiëntportaal (`privateforms`) bouwt
op deze endpoints, maar dat werk staat niet in deze tickets.

Bronnen:
- Design: `privateforms/design/patient_desktop.html` ("Mijn zorgtraject", detail, dossier, bestanden, chat).
- [design/api-voorstel.md](../../design/api-voorstel.md): §2 kritiek K1–K17, §4 patiënt-requirements.
- [kliniekportaal-api-gap-analyse.md](../kliniekportaal-api-gap-analyse.md): gedeelde fundamenten (G-6
  `document_type`) en de naamgeving.

## Begrippen

| Portaal (design) | CRM / API |
|---|---|
| Zorgtraject | **sale** (`SalesLead`, Herniapoli én Privatescan; één persoon kan er meerdere hebben) |
| Afspraak | `Order` (id `order-{id}`, zie `PatientPortalAppointmentResource`) |
| Document | gepubliceerde `ActivityFile` (`publishedToPortalForPerson`) |
| Bericht | `PatientMessage` (patiënt ↔ team), nooit `Activity type=note` (team-intern) |

## Afspraken voor de API-laag (gelden voor alle tickets in deze map)

1. **Naamgeving: sale/sales** in code, routes en API (besluit 2026-11-05, gap-analyse). "Traject" of
   "trajectory" alleen als vertaling van het design, nooit in nieuwe code of endpoints.
2. **Routes in de huidige vorm:** `patient/{id}/…` met `api.key` + `patient.self:id`, tot ticket 04 de
   `me/`-vorm levert. De response-vorm blijft dan gelijk.
3. **Resource-links:** elke verwijzing naar een andere entiteit is een object `{ "id": "…", "label": "…" }`.
   `label` is direct toonbaar, `id` haalt de details op via het endpoint van die entiteit. Geen geneste
   kopieën van andere resources.
4. **Eigen patiënt-resources:** alleen expliciete velden. Niets uit de kliniekportaal-resources
   (`ClinicSalesLead*`) hergebruiken; interne velden (arts, beoordeling, co-persons, financieel, interne
   fases) gaan nooit naar de patiënt.
5. **Patiëntstatus is een groepering van interne fases**, als mapping in code (ticket 01).
6. **GET heeft geen zijwerking.** Lezen markeren gaat via een aparte `POST`.
7. **Paginering en requests** volgen `PatientPaginatedIndexRequest`; nieuwe lijsten gebruiken
   `PatientPaginatedCollection`.

## Tickets

| # | Ticket | Afhankelijk van | Grootte | Prio |
|---|---|---|---|---|
| 01 | [Patiëntstatus van een sale](01-patient-sale-status.md) | – | S | M |
| 02 | [Endpoint sales: lijst + detail](02-sales-endpoint.md) | 01 | M | M |
| 03 | [`sale_id`-filter op afspraken en documenten](03-sale-filter.md) | – | S | M |
| 04 | [Auth: `patient/me/*` en Person in de middleware](04-auth-me-routes.md) | – | M | M |
| 05 | [Security-fixes bestaande patiënt-API](05-security-fixes.md) | – | S | M |
| 06 | [Wachtwoord alleen in Keycloak](06-password-keycloak-only.md) | 05 | M | M |
| – | [Documenttypes: beheerbare collectie + beheerscherm](../portaal/01-document-type.md), **gedeeld, staat in `tickets/portaal/`** | – | L | **hoog** |
| 08 | [Documenten: gezien-status en filters](08-documents-seen-and-filters.md) | portaal/01 | S | M |
| 09 | [Patiënt-upload: soort, sale en verwerkingsstatus](09-patient-upload.md) | portaal/01 | M | M |
| – | [Afspraak-detail en stabiele afspraak-id's](../portaal/02-appointment-detail.md), **gedeeld, staat in `tickets/portaal/`** | portaal/01 | M | **hoog** |
| 11 | [Berichten: categorie, sale, bijlagen, cursor](11-messages.md) | – | M | M |
| 12 | [Meldingen: lezen zonder zijwerking, read/dismiss](12-notifications.md) | – | S | M |
| 13 | [Persoonsgegevens: whitelist, huisarts/noodcontact, correctieverzoek](13-person-details.md) | – | M | M/S |
| 14 | [Formulieren per sale](14-forms.md) | 02 | M | M |
| 15 | [Patiëntacties (`actions`)](15-actions.md) | 09, 14 | M | M |
| 16 | [`counters` uitbreiden tot dashboard](16-dashboard.md) | 02, 08, 11, 15 | M | M |
| 17 | [Betaalstatus per sale](17-payment-status.md) | 02 | S | M |
| 18 | [Features per sale-type](18-sale-features.md) | 02 | S | M |
| 19 | [Afspraakverzoek wijzigen/annuleren](19-appointment-requests.md) | portaal/02 | S | S |
| 20 | [Tijdlijn per sale](20-sale-timeline.md) | 01, portaal/02 | M | S |
| 21 | [Voorkeuren per kanaal en reminder-stop](21-preferences-reminders.md) | 15 | S | S |

Volgorde: portaal/01 (voorrang) → 05 (snel, risico weg) → 04 → 01–03 → portaal/02 → 08–09 → 11–13 → 14–15 → 16–18 → S-tickets.

## Gedeeld met het kliniekportaal

Tickets die beide portalen nodig hebben staan in [`../portaal/`](../portaal/00-plan.md) en krijgen voorrang.

## Bewust niet (nog)

- Patiëntfacturen en offertes (FIN-001/004): bron onbekend (V-P5 in het api-voorstel).
- Afspraak zelf inplannen (APPT-005) en toestemmingen (TRJ-006): pas na het kliniekportaal (G-4).
- Alles aan de portaalkant (`privateforms`).

## Open vragen (uit api-voorstel §7, nodig voor deze tickets)

- V-P1: roept een backend de patiënt-routes aan met de API-key, of alleen de browser met het patiënttoken? (04)
- V-P3: welke Privatescan-fases zijn een mijlpaal voor de patiënt? (01)
- V-P6: welke berichtcategorieën, en naar welke groep? (11)
- V-P7: moeten patiënt-uploads goedgekeurd worden? (09)
- V5 (gap-analyse): één operatie per order of meer? (10)
