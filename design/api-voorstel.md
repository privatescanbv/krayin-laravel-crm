# API-voorstel: kliniekportaal en nieuw patiëntportaal

- **Status:** voorstel (2026-11-03), nog niet besproken, niets gebouwd
- **Bronnen:** `tickets/kliniekportaal-api-gap-analyse.md` (§6 API-oppervlakte), requirements patiëntportaal
  (DASH/APPT/DOC/FORM/TRJ/MSG/DATA/FIN/NOT), huidige `routes/api.php` en de `Api/Patient*`-controllers.
- **Architecture Mode:** dit raakt auth, publieke API-contracten en het datamodel van meerdere modules.
  Eerst bespreken, dan bouwen.

## 1. Uitgangspunten

1. **Eén domeinmodel, twee portalen.** Beide portalen praten over dezelfde dingen:

   | Portaalbegrip | CRM | Opmerking |
   |---|---|---|
   | Traject (trajectory) | `SalesLead` | Herniapoli én Privatescan. Eén persoon kan meerdere trajecten hebben |
   | Afspraak (appointment) | `Order` + `OrderItem` + boeking (`ResourceOrderItem`) | zie §5, V5: afspraak = boeking, niet order |
   | Document | `Activity type=file` + `activity_files` | zichtbaarheid via `activity_portal_persons` (patiënt) of traject-scope (medewerker) |
   | Bericht | `PatientMessage` (patiënt ↔ team) / `Activity type=note` (team-intern) | die twee mogen nooit door elkaar lopen |
   | Actie (patiënt) | afgeleid: open formulier, gevraagd document, open betaling, afspraakverzoek | geen nieuwe tabel, zie §4.2 |

2. **Elk portaal heeft eigen API Resources.** Het patiëntportaal krijgt nooit dezelfde Resource als het
   kliniekportaal. Medische velden voor medewerkers, financiële velden (OP-techniek, inkoop, interne notities)
   en interne fases komen nooit in een patiënt-Resource terecht (DOC-004, FIN-005, DASH-004).
3. **Rechten op één plek.** Patiënt: de persoon uit het token, verder niets. Medewerker:
   `SalesLead::scopeVisibleInPortalFor(User)` en een Policy. Afspraken, documenten en berichten erven hun
   zichtbaarheid van het traject.
4. **Patiëntvriendelijke status is configuratie, geen code.** Zie §4.1.
5. **Notificaties zijn neutraal** (MSG-005, NOT-002). De payload bevat een type en een referentie, nooit
   een uitslag, diagnose of berichtinhoud.

---

## 2. Kritiek op de huidige patiënt-API

Wat er nu staat werkt, maar het nieuwe portaal bouwt erop voort. Deze punten wil ik eerst rechtzetten.

| # | Bevinding | Waar | Gevolg | Voorstel |
|---|---|---|---|---|
| K1 | De patiënt wordt aangeduid met de **Keycloak-id in het pad** (`patient/{id}`). Bij een bearer-token moet die gelijk zijn aan `sub`: het pad herhaalt dus het token | `EnsureKeycloakPatientMatchesRoute` | elke client moet de sub uit het token halen en in de URL zetten; Keycloak-id's lekken in logs en URL's | `patient/me/...` voor bearer-callers. `{id}` blijft alleen voor API-key (service-to-service). Eén middleware zet de `Person` op de request, voor beide vormen |
| K2 | Elke controller doet zelf `resolvePersonOrUser()` en de afhandeling "user zonder person → lege lijst" | alle 10 `Patient*`-controllers | gedrag loopt uiteen: `messages` geeft 200 met lege data, `password` 404, `store` logt een error en geeft 200 | resolven in de middleware van K1; controllers krijgen een `Person`. Medewerker-accounts op het patiëntportaal: één keer besluiten (advies: 403) |
| K3 | **API-key heeft volledige toegang tot alle patiënten.** `patient.self` controleert niets zonder bearer | `EnsureKeycloakPatientMatchesRoute` r.25 | elke API-key-houder (n8n, forms) kan elk dossier lezen en bewerken | per API-key scopes (bijv. `patient:read`), of de patiënt-routes alleen voor bearer openzetten en de BFF het patiënttoken laten doorsturen. Zie V-P1 |
| K4 | `PUT patient/{id}/password` met bearer **vraagt het huidige wachtwoord niet** | `PatientPasswordController::update` | een gestolen of nog open sessietoken is genoeg om het account over te nemen | `current_password` altijd verplicht; of wachtwoordwijziging helemaal via Keycloak (account console / required action) |
| K5 | Het CRM kan het patiëntwachtwoord **terug-ontsleutelen** (`getDecryptedPassword()`) en zet het op `Person` én `User` | `PatientPasswordController` | omkeerbaar opgeslagen wachtwoorden; dubbele bron naast Keycloak | wachtwoord alleen in Keycloak. CRM zet het door via de Keycloak admin-API en slaat het niet op. Apart ticket (security) |
| K6 | `password/reset` hangt in de groep waar de patiënt al ingelogd moet zijn, en weigert daarna juist bearer-callers | `routes/api.php` r.144 | de route staat op de verkeerde plek; de bedoeling valt niet uit de route af te lezen | naast `patient/forgot-password` zetten: `POST patient/password/reset` (API-key, met `reset_token`) |
| K7 | **`GET notifications` markeert als gelezen** (zijwerking bij lezen) | `PatientNotificationController::index` | een prefetch, retry of tweede tabblad markeert alles als gelezen; DASH-003 ("nieuw") kan niet betrouwbaar | GET is alleen lezen. Lezen markeren via `POST notifications/read` (ids of `all`) |
| K8 | `markAsRead` zet **`dismissed_at`**, en weigert niet-dismissable meldingen | idem | "gelezen" en "weggeklikt" zijn één ding | twee acties: `read` (altijd mag) en `dismiss` (alleen dismissable) |
| K9 | `counters.new_docs_count` telt **open formulieren**, geen documenten | `PatientCounterController` r.58 | de naam liegt; DASH-003 (nieuwe documenten) kan er niet op leunen | opgaan in `GET patient/me/dashboard` met correcte namen (`unread_messages`, `new_documents`, `open_forms`, `open_actions`) |
| K10 | `messages` is oplopend gesorteerd én gepagineerd: pagina 1 is het **oudste** bericht | `PersonActivityController::index` | het portaal moet alle pagina's ophalen om het laatste bericht te tonen | aflopend + cursor-paginering (`?before=`); de client draait om voor weergave |
| K11 | `messages` kent geen **categorie, traject of bijlage**; `mark_as_read` markeert altijd alles | `PatientMessage` | MSG-001/002/004/006 kunnen niet | kolommen `category`, `sales_lead_id`; bijlagen als file-activiteit aan het bericht; `read` met `up_to_id` |
| K12 | `POST messages` logt de volledige request-body (`print_r($request->all())`) | `PersonActivityController::store` r.81 | medische berichtinhoud in de applicatielog (AVG) | logregel weg |
| K13 | Patiënt-upload heeft geen **document_type, traject of status**; een rare closure-validatie doet niets | `PatientFileUploadController` | DOC-002/003/005 kunnen niet; upload komt "los" op de persoon | velden `document_type`, `sales_lead_id`, optioneel `request_id`; status zie §4.3; de lege closure weg |
| K14 | **NAW-update laat kerngegevens wijzigen** (naam, geboortedatum, geslacht) | `PatientNawController::update` r.104 | in strijd met DATA-004; identiteit kan zonder controle veranderen | whitelist: alleen contactgegevens + adres. Kerngegevens via een correctieverzoek (`POST me/details/correction-requests` → taak in werkvoorraad) |
| K15 | Afspraak-id's zijn strings `"order-123"` / `"activity-123"`; `practitioner_id`, `end_at`, `is_remote` zijn altijd null/false | `PatientPortalAppointmentResource` | id's zijn niet stabiel als één order meerdere afspraken krijgt; dode velden in het contract | afspraak-id = boeking-id (zie V5); dode velden weg of vullen (type, locatie, eindtijd) |
| K16 | Kleiner: het dubbele `sales-leads`-blok heet in het commentaar "singular", maar het prefix is hetzelfde | `routes/api.php` r.72 | twee keer dezelfde route registreren; het enkelvoudige n8n-pad bestaat niet | kijken wat n8n echt aanroept; óf `sales-lead` maken, óf het blok weghalen |
| K17 | Kleiner: de `patient/*`-routes hebben geen rate limit (alleen forgot-password en 2FA) | `routes/api.php` | uploads en berichten zonder rem | `throttle:patient` op de groep |

**Wat goed is en blijft:** publicatie via `activity_portal_persons` (DOC-004 werkt al zo), `PatientNotification`
met `reference_type/id` en `expires_at`, de preferences met defaults en `is_system_managed`, en de 404 bij
documenten die niet gepubliceerd zijn.

---

## 3. Kliniekportaal-API (`api/portal/*`)

Uitgangspunt is §6 van de gap-analyse. Auth: middleware `keycloak.staff`, bearer van `clinic-app` /
`employee-app`, `User` via `keycloak_user_id`, rol Kliniek / Arts / Zorgadviseur. **API-keys hebben hier
geen toegang.**

### 3.1 Correcties op de lijst uit de gap-analyse

| Gap-lijst | Voorstel | Waarom |
|---|---|---|
| `GET portal/home/version` (polling) | weg; `GET portal/home` met `ETag` / `If-None-Match` → `304` | standaard HTTP doet dit al; een endpoint minder |
| `POST portal/documents/links` | opgaan in `POST portal/trajectories/{id}/documents` met `kind=link` | een link zonder traject heeft geen scope en dus geen autorisatie |
| `portal/trajectories/{id}/notes` naast `/messages` | één bron: `messages` met `visibility=team`; notes zijn berichten zonder draad | beide zijn al `Activity type=note`; twee endpoints voor één model |
| `portal/appointments/{id}` (id = ?) | id = boeking (`ResourceOrderItem`), met `order_id` en `trajectory_id` erbij | één order kan operatie + consult + nacontrole bevatten (V5) |
| `PATCH .../clinical-record` | blijft, met `If-Match` (updated_at/ETag) i.p.v. een eigen `verwacht`-veld | zelfde conflictcontrole als `PATCH trajectories/{id}`; 412 bij conflict |
| `POST portal/appointments/{id}/documents` en `.../trajectories/{id}/documents` | blijven allebei, één controller, `Idempotency-Key`-header | recept/"opnieuw versturen" (G-6a) mag geen dubbel document geven |

### 3.2 Routes

```
GET    portal/me                                   rol, capabilities, kliniek
GET    portal/home                                 blokken per rol (G-9), ETag
GET    portal/users                                portaalgebruikers voor @mentions (naam, avatar; geen e-mail)

GET    portal/trajectories?status=open|closed&q=   werkvoorraad, cursor-paginering, lichte kaartvorm
GET    portal/trajectories/{id}                    dossierkop + overzicht
PATCH  portal/trajectories/{id}                    diagnosis, treatment_advice, region, see_chat (whitelist per rol, If-Match)
PUT    portal/trajectories/{id}/work-status        10 statussen + sluitcontrole server-side
GET    portal/trajectories/{id}/appointments
GET    portal/trajectories/{id}/documents          gegroepeerd per document_type, met aanwezig/ontbreekt
POST   portal/trajectories/{id}/documents          kind=file|link, document_type, share_with_patient, Idempotency-Key
GET    portal/trajectories/{id}/messages           ?visibility=team (default); draad via parent_id
POST   portal/trajectories/{id}/messages           body, parent_id, mentions[], bijlage

GET    portal/appointments?from=&to=&type=         week/periode
GET    portal/appointments/{id}
POST   portal/appointments                         plannen (operatie/consult/nacontrole), status optie|ingepland
PATCH  portal/appointments/{id}                    planningsvelden (whitelist), If-Match
POST   portal/appointments/{id}/cancel             reden verplicht
GET    portal/availability?from=&days=&type=       afgeleid uit Shift + boekingen
GET    portal/appointments/{id}/clinical-record
PATCH  portal/appointments/{id}/clinical-record    veldrechten per rol, If-Match
GET    portal/appointments/{id}/documents
POST   portal/appointments/{id}/documents          OP-bericht e.d., Idempotency-Key

GET    portal/documents/{id}/download              302 naar temporarySignedRoute (5 min)
DELETE portal/documents/{id}                       alleen eigen upload, nooit gegenereerd/gedeeld
DELETE portal/messages/{id}                        alleen eigen bericht (soft delete, "verwijderd" blijft zichtbaar)

GET    portal/notifications                        alleen lezen
POST   portal/notifications/read                   ids[] of all
```

24 endpoints. Het verschil met de gap-lijst komt door de samenvoegingen uit §3.1.

---

## 4. Nieuw patiëntportaal (`api/patient/me/*`)

Auth: bearer van de patiënt (`sub` → `Person`). Zie K1 en K3 voor de API-key-variant.

### 4.1 Patiëntvriendelijke trajectstatus (DASH-004, TRJ-001)

Advies: een kolom `patient_status` op `lead_pipeline_stages` (enum, beheerd in de pipeline-instellingen),
geen mapping in code. Voorstel voor de waarden:

| `patient_status` | Voorbeeld-salesfases |
|---|---|
| `received` (aanvraag ontvangen) | intake |
| `in_review` (in beoordeling) | casus bij arts ter beoordeling, MRI-beoordeling |
| `decision` (besluit / bedenktijd) | beoordeling gereed, bedenktijd, zorgverzekeraar |
| `scheduled` (ingepland) | gepland |
| `aftercare` (nazorg) | 1e / 2e nazorg |
| `completed` (afgerond) | succesvol afgerond |
| `closed` (gestopt) | niet succesvol afgerond |

De interne `work_status` (G-3) gaat **nooit** naar de patiënt.

### 4.2 Patiëntacties (DASH-002, NOT-004)

Geen nieuwe tabel. `GET me/actions` voegt bestaande bronnen samen. Elke bron weet zelf wanneer de actie klaar is:
dat is ook het stopsignaal voor reminders (NOT-004).

| Actie-type | Bron | Klaar wanneer |
|---|---|---|
| `form` | `FormService::getOpenForms` | formulier definitief verzonden |
| `document_request` | file-verzoek: `Activity type=task` met `additional.requested_document_type`, gepubliceerd naar de patiënt | upload met dat `request_id` ontvangen |
| `payment` | `OrderPayment`/openstaand bedrag | betaald (FIN-002) |
| `appointment_confirm` | `OrderPersonConfirmation` | bevestigd |

`document_request` is het enige nieuwe concept. Het hergebruikt Activity en de portal-publicatie.

### 4.3 Uploadstatus (DOC-003)

`additional.processing_status` op de file-activiteit: `received` → `in_review` → `accepted` | `rejected`
(met een patiëntvriendelijke `rejection_reason`). Het CRM-scherm zet de status. Eigen uploads zijn zichtbaar voor de
patiënt, ook zonder publicatie; het filter `?source=own`.

### 4.4 Routes per requirement

Legenda: ✅ bestaat · 🔧 bestaat, aanpassen · 🆕 nieuw · — geen CRM-werk

| Req | Prio | Endpoint | Status | Toelichting |
|---|---|---|---|---|
| **Dashboard** |||||
| DASH-001…005 | M/S | `GET me/dashboard` | 🆕 | samengesteld: `next_appointment`, `open_actions` (aantal + eerste 3), `unread_messages`, `new_documents`, `trajectories[]` (id, titel, `patient_status`), `contact` (uit `config/patient_portal.php`). Vervangt `counters` (K9) |
| DASH-003 | M | `POST me/documents/{id}/seen` | 🆕 | leesstatus per document (pivot `activity_portal_persons.seen_at`) |
| **Afspraken** |||||
| APPT-001 | M | `GET me/appointments?filter=future\|past&trajectory_id=` | 🔧 | bestaat; `trajectory_id` + stabiele id's (K15) |
| APPT-002 | M | `GET me/appointments/{id}` | 🆕 | type (producttype, patiëntvriendelijke naam), start/eind, locatie + adres + route-url (kliniek), voorbereiding (documenten met `document_type=preparation` per producttype) |
| APPT-003 | S | — | — | de client maakt de `.ics` uit APPT-002 |
| APPT-004 | S | `POST me/appointments/{id}/requests` | 🆕 | `type=change\|cancel`, reden, voorkeur. Maakt een taak-activiteit op de order (werkvoorraad); idempotent per afspraak zolang er een open verzoek is |
| APPT-005 | C | `GET me/availability`, `POST me/appointments` | later | dezelfde availability-service als `portal/availability`; pas bouwen als G-4 er staat |
| **Documenten** |||||
| DOC-001 | M | `GET me/documents`, `GET me/documents/{id}/download` | 🔧 | bestaat; download via signed URL (zelfde als portaal), `seen`-vlag erbij |
| DOC-002 | M | `POST me/documents` | 🔧 | + `document_type`, `trajectory_id`, `request_id` (K13) |
| DOC-003 | M | `GET me/documents?source=own` | 🆕 | `processing_status`, zie §4.3 |
| DOC-004 | M | — | ✅ | `publishedToPortalForPerson` filtert al. Wel een test toevoegen die concept/intern expliciet uitsluit |
| DOC-005 | S | `?trajectory_id=&category=` | 🔧 | `category` = groep van `document_type` (enum gedeeld met G-6) |
| DOC-006 | C | `kind=link` in de documentlijst | later | hergebruikt de G-6-viewerlink; alleen tijdelijke URL, nooit de vaste link |
| **Vragenlijsten** |||||
| FORM-001, 005 | M/S | `GET me/forms?status=open\|submitted&trajectory_id=` | 🔧 | toewijzing, status, deadline, geschatte duur, open-url naar Forms. Bron: Anamnesis/GVL + `FormService` |
| FORM-002, 003 | M | — | — | Forms is bronhouder |
| FORM-004 | M | `PUT api/forms/{id}/status` (API-key, Forms → CRM) | 🔧 | status `submitted` + versie; daarna in CRM alleen-lezen. Nagaan of `leads/{id}/forms` dit al dekt |
| FORM-006 | M | `POST api/forms/{id}/alerts` (API-key, Forms → CRM) | 🆕 | maakt een taak met hoge prioriteit op het traject + medewerkermelding (G-8). Idempotent op `alert_key` |
| **Traject** |||||
| TRJ-001 | M | `GET me/trajectories?status=active\|closed` | 🆕 | type (afdeling + producttype), organisatie/kliniek, startdatum, `patient_status` |
| TRJ-002 | M | `GET me/trajectories/{id}` | 🆕 | kop + samenvatting (eerstvolgende afspraak, open acties, aantal documenten). Details via de lijsten met `?trajectory_id=`; geen geneste kopieën van elke route |
| TRJ-003 | M | in TRJ-002: `type`, `features[]` | 🆕 | `features` uit config per trajecttype (bijv. `forms`, `payments`, `results`); de frontend toont blokken op basis daarvan |
| TRJ-004 | M | `GET me/documents?category=results&trajectory_id=` | 🔧 | uitslag/advies zijn documenten met een categorie. Zichtbaar zodra gepubliceerd; geen apart endpoint |
| TRJ-005 | S | `GET me/trajectories/{id}/timeline` | 🆕 | afgeleid: wissels van `patient_status` (uit het Wijzigingslogboek) + afspraken + gepubliceerde uitslagen. Alleen mijlpalen, geen interne fases |
| TRJ-006 | C | `POST me/trajectories/{id}/consents` | later | toestemming met audit; pas als duidelijk is met wie gedeeld wordt |
| **Berichten** |||||
| MSG-001, 004 | M/S | `POST me/messages` | 🔧 | + `category` (verplicht), `trajectory_id`, bijlagen (zelfde regels als upload). Routering: categorie → groep/werkvoorraad |
| MSG-002 | M | `GET me/messages?trajectory_id=&before=` | 🔧 | aflopend, cursor (K10); `POST me/messages/read {up_to_id}` (K11) |
| MSG-003 | M | — | — | tekst in de frontend |
| MSG-005 | M | — | 🔧 | mailtekst van de bestaande meldingsmail controleren: alleen "je hebt een nieuw bericht" + link |
| MSG-006 | S | `GET me/message-categories` | 🆕 | categorieën met `expected_response` uit `config/patient_portal.php` |
| **Persoonlijke gegevens** |||||
| DATA-001, 004 | M | `GET me/details` | 🔧 | huidige `naw` + `editable_fields[]` + `updated_at` |
| DATA-002 | S | `PATCH me/details` | 🔧 | alleen contactgegevens en adres (K14); audit loopt al via `HasAuditTrail` |
| DATA-003 | S | in `me/details`: `general_practitioner{name,practice,phone}`, `emergency_contact{name,relationship,phone}` | 🔧 | velden staan op `Person` en in het CRM-formulier (2026-11-03); nog niet in de patiënt-API |
| DATA-004 | M | `POST me/details/correction-requests` | 🆕 | correctieverzoek voor kerngegevens → taak in de werkvoorraad |
| **Financieel** |||||
| FIN-001, 004 | M/S | `GET me/financial-documents` | 🆕 | offertes, facturen, betalingsbewijzen. **Bron onbekend**: `Inkoop*` is inkoop, geen patiëntfactuur (V-P5) |
| FIN-002 | M | in `me/trajectories/{id}`: `payment_status` | 🆕 | `open` / `partially_paid` / `paid` / `refunded`, afgeleid uit `OrderPayment` tegen het orderbedrag |
| FIN-003 | S | `POST me/payments` + webhook `POST api/payments/webhook` | later | betaalprovider maakt de betaling; idempotent op order + openstaand bedrag, zodat er niet dubbel betaald wordt |
| FIN-005 | M | — | ✅/🔧 | eigen Resource, alleen expliciete velden (uitgangspunt 2) |
| **Notificaties** |||||
| NOT-001, 002 | M | `GET me/notifications` | 🔧 | alleen lezen (K7); neutrale `title`/`summary`, `reference` als type + id |
| NOT-001 | M | `POST me/notifications/read`, `POST me/notifications/{id}/dismiss` | 🔧 | K8 |
| NOT-003 | S | `GET/PUT me/preferences` | 🔧 | bestaat; per kanaal uitbreiden (`email`, `sms`, `push`) in `PersonPreferenceKey` |
| NOT-004 | S | — | 🆕 | de reminder-job vraagt vóór elke verzending de actiestatus op (§4.2); het CRM stuurt geen reminder voor een afgeronde actie |

### 4.5 Overig, niet-patiënt-specifiek

```
POST   patient/forgot-password                 ✅ API-key
POST   patient/password/reset                  🔧 verplaatst uit patient/{id} (K6)
PUT    me/password                             🔧 current_password verplicht (K4), of naar Keycloak (K5)
GET    keycloak/persons/{keycloakUserId}        ✅ overbodig voor bearer zodra me/ bestaat
```

---

## 5. Datamodel (bovenop §7 van de gap-analyse)

| Wijziging | Voor |
|---|---|
| `lead_pipeline_stages.patient_status` (enum) | DASH-004, TRJ-001, TRJ-005 |
| `patient_messages.category`, `patient_messages.sales_lead_id` (nullable) | MSG-001/002 |
| `activity_portal_persons.seen_at` | DASH-003 |
| `activities.document_type` als kolom (enum, gedeeld met G-6) + `processing_status` | DOC-002/003/005, TRJ-004 |
| ✅ `persons.general_practitioner_{name,practice,phone}`, `persons.emergency_contact_{name,relationship,phone}` (gebouwd 2026-11-03) | DATA-003 |
| `config/patient_portal.php`: contact, berichtcategorieën + reactietermijn, features per trajecttype | DASH-005, MSG-006, TRJ-003 |

`document_type` als kolom is ook gap G-6: één migratie voor beide portalen.

## 6. Volgorde

1. **Auth-opschoning patiënt (K1-K6, K12):** `me/`-routes naast de bestaande, middleware die `Person` resolvet,
   security-punten. De oude `patient/{id}`-routes blijven werken tot het oude portaal weg is.
2. **G-1/G-1a kliniekportaal-auth** (zie de gap-analyse). Dit staat los van stap 1 en kan parallel.
3. **Gedeeld fundament:** `document_type`-kolom, `patient_status` op fases, traject-Resource.
4. **Patiënt M-requirements:** dashboard, trajecten, afspraakdetail, documenten (+status), berichten (+categorie), acties.
5. **Kliniekportaal G-2 t/m G-9**, volgens de volgorde uit de gap-analyse.
6. **S/C-requirements** (afspraakverzoek, betalen, tijdlijn, slots, toestemming).

## 7. Open vragen

| # | Vraag | Waarom het uitmaakt |
|---|---|---|
| V-P1 | Roept een backend (BFF, n8n, Forms) de patiënt-routes aan met de API-key, of alleen de browser met het patiënttoken? | Bepaalt of `patient/{id}` met API-key mag blijven en of API-keys scopes nodig hebben (K3) |
| V-P2 | Wie mag het wachtwoord zetten: Keycloak alleen, of ook het CRM? | K4/K5. Advies: alleen Keycloak |
| V-P3 | Welke Privatescan-fases zijn voor de patiënt een mijlpaal? | Vulling van `patient_status` (§4.1) |
| ~~V-P4~~ | ~~Huisarts/noodcontact-datamodel~~ | **Besloten:** optionele losse velden op `Person`, één noodcontact, geen AGB-code |
| V-P5 | Waar staan patiëntfacturen en offertes: in het CRM (te bouwen), in het boekhoudpakket, of bij de betaalprovider? | FIN-001/004 zijn zonder bron niet te bouwen |
| V-P6 | Welke berichtcategorieën, en naar welke groep/werkvoorraad gaat elk? | MSG-001-routering |
| V-P7 | Moeten patiënt-uploads in het CRM worden goedgekeurd (`accepted`/`rejected`), of is `received` genoeg? | DOC-003; bepaalt of er CRM-UI nodig is |
| V5 (gap) | Eén operatie per order, of meerdere? | Bepaalt het afspraak-id voor beide portalen (K15, §3.1) |
