# Gap-analyse: CRM API-laag vs. ontwerp Kliniekportaal (Herniapoli)

- **Status:** analyse, eerste open vragen beantwoord (2026-10-29); nog geen bouw
- **Besluiten (2026-10-29):**
    - Patiëntbegeleider komt **niet** in het portaal.
    - De arts wordt CRM-gebruiker en wordt **toegekend aan een sale** (SalesLead). Dat bestaat nog niet en is onderdeel van de gap (G-1a).
    - Patiënten en dossiers aanmaken gebeurt **in het CRM**, niet in het portaal.
    - De opmerking "CRM-integratie geparkeerd" in het monday-project is een oud besluit en vervalt.
    - **Eén arts per sale, verplicht, alleen voor Herniapoli.** Privatescan-sales krijgen deze relatie niet (geen operaties).
    - **De arts heeft een relatie met een kliniek** (te bouwen). Daarmee ligt vast welke patiënten een kliniek mag
      zien: kliniek → artsen van die kliniek → hun sales. Meerdere klinieken zijn dus mogelijk en worden gescheiden.
    - Een arts werkt bij **één** kliniek (`users.clinic_id`).
    - De arts is **verplicht vanaf de overgang naar "Casus bij arts ter beoordeling"**, niet bij het aanmaken van de sale.

## 1. Uitgangspunten

**Bron van het ontwerp:** `~/Downloads/Herniapoli-monday` (v45 Draft). Dit is een werkende monday.com-app.
Het datacontract komt daarom uit code en niet uit losse mockups:

| Bron | Wat het vastlegt |
|---|---|
| `apps/herniapoli-gateway/src/routes/dispatch.js` | alle ≈ 60 operaties die de frontend aanroept |
| `shared/resource-map.js` | ≈ 70 logische velden, documentregels en planningsconstanten |
| `shared/access-model.js` | rollen, capabilities en veldcategorieën |
| `docs/HOME_ROLSCHERMEN.md` | het datacontract van Home per rol |
| `docs/benutzerhandbuch-klinik`, `gebruikershandleiding-arts`, `-zorgadviseur` | schermen en workflows (screenshots en tekst) |
| `docs/00_Procelsio_monday_Master_Blueprint_v1.1.docx` | de functionele waarheid (besluiten) |

**Scope (afgesproken):**

- Het kliniekportaal is één portaal voor de rollen **Kliniek**, **Arts** en **Zorgadviseur**, elk met een eigen rol en
  mogelijk eigen schermen.
- **Patiënten krijgen geen toegang** tot het kliniekportaal. Het patiëntportaal blijft los bestaan.
- Een groot deel blijft in de huidige structuur. De Document Studio (Arztbrief) en de Rezept Studio blijven
  generatoren aan de portaalkant. Het CRM bewaart alleen het resultaat als bestand.
- **Een recept gaat als file-activiteit naar het patiëntportaal.** Daarvoor bestaat al het mechanisme
  `activity_portal_persons` + `PatientNotification`.

**Buiten scope:** de rollen Patiëntbegeleider en Beheerder uit het ontwerp (besloten), patiënten en dossiers
aanmaken vanuit het portaal (dat blijft in het CRM), migratie van monday-data, en het dashboard.

---

## 2. Domeinvertaling monday → CRM

Het ontwerp gebruikt twee monday-borden. Dit is de vertaling naar ons model. Een gap-regel verwijst hiernaar.

| Ontwerp (monday) | CRM | Opmerking |
|---|---|---|
| Patiënt (recordtype op patiëntenbord) | `Person` | naam, voornaam en geboortedatum aanwezig |
| Behandeltraject (LWS/HWS-dossier) | `SalesLead` met `department = Herniapoli` | één patiënt kan meerdere trajecten hebben; dat klopt al (`lead_persons`/`persons()`) |
| Regio (LWS / HWS) | niet als veld; af te leiden uit `FormType::HerniaBackPainForm` / `HerniaNeckPainForm` | **gap**, zie G-2 |
| Werkstatus (10 statussen, "wie is aan zet") | `SalesLead.pipeline_stage_id` (Hernia-saleslijn) | **andere as**, zie G-3 |
| Diagnose / Behandlungsberatung | niet aanwezig; wel `assessment_outcome` (code) | **gap**, zie G-2 |
| Operatie-afspraak (operatiebord) | `Order` (Hernia-orderlijn) + `OrderItem` (partnerproduct, bv. "PTED - operatie 1 niveau") + `ResourceOrderItem` (from/to op een `Resource`) | basis aanwezig |
| Consult / Nacontrole | `OrderItem` met partnerproduct "Consultgesprek arts" / "Nacontrole Dr. Jost Thissen" + boeking | aanwezig, te verifiëren of dit zo gebruikt wordt |
| Operatierecord (klinische historie per operatie) | `Order` zelf | klinische velden ontbreken, zie G-5 |
| Vrij slot / capaciteit | `Resource` + `Shift` (beschikbaarheidsblokken) | ander concept: geen losse slot-items, zie G-4 |
| Planningsstatus Optie / Ingepland / Geannuleerd | `OrderItemStatus` + orderfase | "Optie met vervaldatum" ontbreekt, zie G-4 |
| Kliniek (Procelsio) | `Clinic` "Procelsio Clinic GMBH" + `ClinicDepartment` | aanwezig |
| Bestandskolommen (Diagnoseformulier, 1e MRT, AFB, ...) | `Activity type=file` + `additional.document_type`; deels eigen modellen (Anamnesis/GVL-form, `AfbPersonDocument`, `leads.diagnoseform_pdf_url`) | verspreid, zie G-6 |
| Updates/chat per dossier (draad, @mention, bijlage) | `Activity type=note` op sales_lead (plat) | **gap**, zie G-7 |
| Interne notitie | `Activity type=note` | aanwezig |
| Inbox/meldingen voor medewerkers | niet aanwezig (`PatientNotification` is alleen voor patiënten) | **gap**, zie G-8 |
| Anesthesiestatus / -formulier | `FormType::HerniaNarcoseForm` (GVL-flow op Anamnesis/Order) | formulier wel, beoordelingsstatus niet, zie G-5 |

---

## 3. Huidige API-laag (stand op branch `feat/mbs-505-…`)

`routes/api.php`: alle routes staan achter `api.key`.

| Groep | Endpoints | Auth |
|---|---|---|
| Leads | `POST leads/hernia`, `POST leads/privatescan`, `GET/PUT/DELETE leads/{id}`, stage, notes, activities, forms | alleen X-API-KEY |
| Sales-leads | `POST sales-leads`, `GET/POST sales-leads/{id}/activities` | alleen X-API-KEY |
| Patiënt (portaal) | `patient/{id}/messages, counters, appointments, documents (+upload/download), notifications, naw, password, preferences` | Keycloak Bearer **alleen voor eigen subject** (`patient.self`) |
| Overig | webhooks, keycloak-mapping, 2FA-code, forgot-password, failed-jobs | alleen X-API-KEY |

**Kernconclusie:** de API heeft twee soorten ingangen:

1. **Service-to-service** (API-key), zonder gebruikersidentiteit en zonder rechten per gebruiker.
2. **Patiënt-self**, waarbij een token alleen bij de eigen gegevens kan.

Er is **geen ingang voor een ingelogde medewerker (kliniek, arts of zorgadviseur) met rolgebaseerde rechten.**
`ApiKeyAuth::allowsKeycloakBearerFallback()` laat een bearer-token bewust alleen toe op `api/patient/*`.
Dat is gap G-1, en alle andere gaps hangen ervan af.

De bouwstenen voor die ingang zijn er al: Keycloak-clients `clinic-app` en `employee-app`
(`KeyCloakClient`) en de realm-rollen `clinic` en `employee` (`KeycloakRoles`).

---

## 4. Gaps

Legenda: ✅ aanwezig · 🟡 deels / alleen in admin-web (sessie) · ❌ ontbreekt

### G-1 Authenticatie en autorisatie voor medewerkers ❌ (blokkerend)

| Ontwerp | CRM | Gap |
|---|---|---|
| Ingelogde gebruiker met rol advisor / doctor / clinic (`roles.current`) | Keycloak-clients `clinic-app`/`employee-app` bestaan; API accepteert hun token niet | Bearer-auth voor medewerkers op een nieuwe routegroep, gekoppeld aan een CRM-`User` |
| Capabilities per rol (≈ 40, zie `access-model.js`) | Webkul `Role` + ACL-permissies (admin-web) | capabilities voor het portaal bepalen; Laravel Policies/Gates op de API |
| Veldrechten per categorie (medisch / kliniek / administratief / financieel) | niet aanwezig | **Advies:** niet het hele veldclassificatiemodel kopiëren. Per rol een vaste API Resource; "financieel" (OP-techniek, prijzen) nooit in portaal-resources |
| Bereik: kliniek ziet alleen eigen patiënten | geen koppeling User ↔ Clinic | kliniekgebruiker én arts koppelen aan `Clinic`; scope via arts → sale (zie G-1a) |
| `session.ping`, `home.versie` (polling) | — | `GET /me` volstaat; versie-polling zie G-9 |

#### G-1a Arts als gebruiker, toegekend aan een sale ❌ (besloten, nog niet gebouwd)

Een arts ziet in het portaal **alleen de trajecten waaraan hij is toegekend**. Op dit moment:

- `SalesLead.user_id` is de eigenaar (de zorgadviseur). Er is geen veld voor een behandelend arts.
- Een arts bestaat nog niet als CRM-`User` met een eigen rol.

Wat er nodig is:

| Onderdeel | Gap |
|---|---|
| `salesleads.doctor_user_id` (FK → users), relatie `SalesLead::doctor()`. Eén arts per sale | ❌ |
| **Verplicht voor Herniapoli-sales vanaf de overgang naar "Casus bij arts ter beoordeling"** (`sales-intake-hernia`), en in alle fases daarna. Nooit voor Privatescan (`SalesLead::isHerniaPoli()`). Afdwingen op elke route die de fase zet (admin-update, stage-wissel, API, automatisering); bij voorkeur op één centrale plek die al die routes gebruiken. Aanmaken zonder arts mag, zodat de automatische flow bij een gewonnen lead blijft werken | ❌ |
| Bestaande Herniapoli-sales hebben nog geen arts: een **backfill** (eenmalig toekennen) voordat de verplichting aan gaat | ❌ |
| CRM-rol "Arts" (Webkul `Role`) + Keycloak-rol/groep, zodat de arts via het portaal inlogt | ❌ |
| Arts toekennen in de CRM-admin (sale-detail; ook in bulk, voor de bestaande werkvoorraad) | ❌ (CRM-UI, geen portaal-API) |
| Wijziging van de toekenning in het Wijzigingslogboek (audit trail) | ✅ via `HasAuditTrail` |

**Arts ↔ kliniek (te bouwen):**

| Onderdeel | Gap |
|---|---|
| `users.clinic_id` (FK → clinics, nullable): één kliniek per arts, en ook voor de kliniekgebruiker | ❌ |
| Verplicht voor gebruikers met rol Arts of Kliniek | ❌ |
| Beheer in de CRM-admin (gebruiker bewerken: kliniek kiezen) | ❌ |

**Scope per rol, afgeleid uit deze twee relaties:**

| Rol | Ziet |
|---|---|
| Zorgadviseur | alle Herniapoli-sales (gezamenlijke werkvoorraad, blueprint) |
| Arts | sales met `doctor_user_id = ik` |
| Kliniek | sales waarvan de arts bij de eigen kliniek hoort: `doctor.clinic_id = mijn clinic_id` |

Deze scope zit als één query-scope (`SalesLead::scopeVisibleInPortalFor(User)`) plus Policy op alle
portaal-endpoints: trajecten, afspraken, documenten, chat en Home. Afspraken en documenten erven hun zichtbaarheid
van de sale; ze krijgen geen eigen check.

Let op: een operatie wordt ingepland op een `Resource` → `ClinicDepartment` → `Clinic`. Bij het plannen
moet die kliniek gelijk zijn aan de kliniek van de arts. Anders ziet de kliniek waar de operatie plaatsvindt
de patiënt niet. Voorstel: bij het boeken valideren en een afwijking weigeren.

**Voorstel:** een aparte routegroep `api/portal/*` met middleware `keycloak.staff`. Die valideert het token,
vindt de `User` via `keycloak_user_id` of e-mail, controleert de rol (`clinic`, `employee` + portaalrol) en
weigert alles zonder rol. API-key-callers horen hier **niet** toegang te krijgen.

### G-2 Behandeltraject (SalesLead) als dossier 🟡

| Ontwerp-operatie | CRM | Gap |
|---|---|---|
| `patients.list` / `itemsOpen` / `itemsAfgerond` (werkvoorraad: patiënt + open trajecten, kaartgegevens) | admin-web kanban `/admin/sales-leads`; geen API-index | ❌ `GET portal/trajectories?status=open\|closed&q=` (gepagineerd, lichte kaartvorm) |
| `patients.detail` / `trajectory` (dossierkop + overzicht) | `SalesLead` + persons + anamnesis | ❌ `GET portal/trajectories/{id}` |
| `patients.itemsPatienten` / `patients.create` / `patients.addTrajectory` (patiënt of dossier aanmaken + duplicaatcontrole) | lead → sales-flow + duplicaatdetectie in het CRM | ✅ **geen gap:** dit blijft in het CRM (besloten). Het portaal kan een knop "openen in CRM" tonen |
| Veld **Regio** (LWS/HWS) | niet aanwezig | ❌ kolom op `salesleads` of afleiden uit formuliertype |
| Veld **Diagnose** (long text, arts) | niet aanwezig | ❌ kolom op `salesleads` |
| Veld **Behandlungsberatung** (long text, arts) | niet aanwezig (`assessment_outcome` is alleen een code) | ❌ kolom op `salesleads` |
| Veld **Trajectnummer** | — | ❌ of `salesleads.id` gebruiken (voorstel: id) |
| Veld **Siehe Chat** (checkbox) | niet aanwezig | ❌ boolean op `salesleads` |
| `patients.updateField` (één veld, met optimistic lock `verwacht`) | — | ❌ `PATCH portal/trajectories/{id}` met whitelist per rol + `updated_at`-check (409 bij conflict) |
| Historie mag nooit overschreven worden | audit trail (`HasAuditTrail`) + Wijzigingslogboek | ✅ wijzigingen loggen als system activity |

### G-3 Werkstatus ❌ (besluit nodig)

Het ontwerp kent **10 werkstatussen** die zeggen wie aan zet is:

| Werkstatus (ontwerp) | Bestaande Hernia-salesfase |
|---|---|
| Casus bij arts ter beoordeling | ✅ `sales-intake-hernia` "Casus bij arts ter beoordeling" |
| Beoordeling gereed | ✅ `sales-mri-beoordeling-hernia` "Beoordeling gereed" |
| Vraag aan arts / Vraag beantwoord | ❌ |
| Artzbrief nodig / Arztbrief verzendklaar | ❌ |
| OP-verslag nodig / OP-verslag verzendklaar | ❌ |
| Wachten | 🟡 deels (bedenktijd, zorgverzekeraar) |
| Afgerond | ✅ succes/niet succesvol afgerond |

De CRM-fases beschrijven de **traject-levenscyclus** (bedenktijd, gepland, nazorg). De werkstatus is een
**taak-as** die er dwars doorheen loopt: een traject in "1e Nazorg" kan tegelijk "OP-verslag nodig" hebben.

**Advies:** een aparte kolom `salesleads.work_status` (enum, 10 waarden) naast de pipeline-fase. Voeg geen
fases toe, want dan breekt de analytics-sync (`003_sync_procedure.sql`). Endpoint:
`PUT portal/trajectories/{id}/work-status`, met de "controle bij het sluiten" uit de handleiding server-side.
Home-blokken (G-9) filteren op deze kolom.

### G-4 Planning: operaties, consulten, nacontroles, capaciteit 🟡

Planning bestaat in het CRM, maar alleen in admin-web (`OrderItemPlanningController`,
`ResourcePlanningMonitorController`, sessie-auth).

| Ontwerp-operatie | CRM | Gap |
|---|---|---|
| `operations.week` / `items` (weekoverzicht: per dag operatie-, intake-, consult- en nacontrolekaarten) | planning-monitor (admin-web) | ❌ `GET portal/appointments?from=&to=` met type, status, patiënt, behandeltype en kamer |
| `operations.detail` (afspraakvenster, 3 tabs) | `Order` + items + boekingen | ❌ `GET portal/appointments/{id}` |
| `operations.forTrajectory` | `SalesLead.orders()` | ❌ `GET portal/trajectories/{id}/appointments` |
| `operations.freeSlots` | `.../availability` (admin-web) | 🟡 ontsluiten als `GET portal/availability?from=&days=` |
| `operations.claimSlot` / `plan` (operatie of consult inplannen; status Optie/Ingepland; behandeltype; intakedatum; aankomsttijd) | `book` (admin-web); Order + OrderItem aanmaken | 🟡 ontsluiten; **Intake-datum** en **aankomsttijd** hebben geen duidelijke plek (`first_examination_at/_time`?, te verifiëren) |
| Status **Optie** + **Optie vervalt** | `OrderItemStatus` heeft geen optie-met-vervaldatum | ❌ status + `option_expires_at` + job die verlopen opties vrijgeeft |
| `operations.cancel` (met reden, historie blijft, optioneel vervangen) | OrderItem-status lost + `lost_reason` | 🟡 ontsluiten; reden verplicht |
| `operations.update` (planningsvelden wijzigen) | `admin.orders.update` | 🟡 ontsluiten met whitelist |
| `operations.capacity` / `capacityDelete` (vrije slots aanmaken/verwijderen, max 20/dag, 180 dagen vooruit) | `Shift` (periode + tijdblokken) op `Resource` | 🟡 **conceptverschil:** het ontwerp maakt losse slots (Slot 1..n per dag), het CRM werkt met beschikbaarheidsvensters. Advies: het CRM-model houden en de portaal-UI slots laten afleiden uit Shift + boekingen. Capaciteitsbeheer in het portaal alleen voor de rol die het echt doet (V4) |
| Weekgroep, slotnummer | monday-artefacten | geen gap; niet overnemen |

### G-5 Operatierecord: klinische velden per operatie ❌

Het ontwerp legt per operatie blijvende klinische data vast. De kliniek vult die in; het Home-blok
"Entlassungsdaten erfassen" schrijft hierheen.

| Veld (ontwerp) | CRM | Gap |
|---|---|---|
| Behandelingstype (PTED 1 niv., Mikro 2 niv., TLIF, ACDF, ...) | `OrderItem.partner_product_id` | ✅ afleidbaar |
| Werkelijke OP-tijd | `ResourceOrderItem.from` is gepland, niet werkelijk | ❌ |
| Kamernummer | — | ❌ |
| Voorovernachting (Übernachtung / Tagesaufenthalt) | partnerproduct "PTED - medische overnachting" als orderregel? | 🟡 te verifiëren; anders boolean |
| Zelfoplossende hechtingen | — | ❌ |
| Bijzonderheden opname | — | ❌ |
| Ontslagdatum / ontslagtijd | — | ❌ |
| Medicatie tijdens opname / meegegeven / receptvoorschrift | — | ❌ (long text) |
| Anesthesiestatus (akkoord / actie nodig / nog niet beoordeeld) + toelichting | Narcoseformulier via GVL-flow, geen beoordelingsstatus | ❌ status + toelichting |
| Aangemeld bij kliniek / documenten opgehaald door kliniek | AFB-verzending (`AfbDispatch.status`, `sent_at`) | 🟡 "aangemeld" ≈ AFB verstuurd; "opgehaald" ❌ (bijvoorbeeld download-timestamp loggen bij portal-download) |
| Tijd bevestigd aan patiënt | `OrderPersonConfirmation` | 🟡 te verifiëren |
| OP-techniek (financieel) | — | nooit in het portaal tonen |

**Advies:** één tabel `order_clinical_records` (1:1 met `Order`, of per OrderItem bij meerdere operaties per
order, zie V5) met de kolommen hierboven. Endpoints: `GET/PATCH portal/appointments/{id}/clinical-record`.
Daarbij **velden per rol**: de kliniek schrijft dag-, opname- en ontslagvelden, de arts schrijft medicatie en
recept. Bij oudere records alleen lezen (blueprint: historie nooit overschrijven).

### G-6 Documenten 🟡

Het ontwerp kent **vaste documentregels** per dossier en per operatie. Home toont per aanmelding of de
verplichte documenten aanwezig zijn (✓ Diagnoseformulier, ⚠ AFB, ...).

| Documentregel | Waar nu in CRM | Gap |
|---|---|---|
| Diagnoseformulier | `leads.diagnoseform_pdf_url` / Anamnesis-formulier (patiëntportaal) | 🟡 één bron kiezen en ontsluiten |
| 1e / 2e / 3e MRT, CT-Bilder, MRT-Befund (ook **externe viewerlinks**) | file-activiteiten, ongetypeerd | 🟡 `document_type` vullen; links als type `link` ❌ |
| Arztbrief I / II, 1e/2e Procelsio-Arztbrief | — | ❌ upload vanuit Document Studio |
| Recept | — | ❌ upload + **delen met patiëntportaal** (zie G-6a) |
| OP-bericht, Procelsio OP-bericht, Behandlungsverlauf | `admin.orders.report-upload` (admin-web) | 🟡 ontsluiten |
| AFB | `AfbPersonDocument` (gegenereerd en verzonden) | 🟡 lezen ontsluiten |
| Anesthesieformulier | GVL Narcose-formulier | 🟡 lezen ontsluiten |
| Medicatielijst | — | ❌ (niet verplicht) |

Wat ontbreekt in de API:

| Ontwerp-operatie | Gap |
|---|---|
| `files.dossier` (alle documentregels van één traject of operatie, met status aanwezig/ontbreekt) | ❌ `GET portal/trajectories/{id}/documents` en `GET portal/appointments/{id}/documents`, gegroepeerd per `document_type` |
| `files.open` (kortlevend ondertekend ticket) | 🟡 de patiënt-download bestaat; voor medewerkers ❌. Advies: `URL::temporarySignedRoute` (5 min) |
| `files.upload` (gechunkt tot 500 MB, 8 MB per stuk) | 🟡 patiënt-upload bestaat. Voor MRI-bestanden ❌ grote/gechunkte upload; eerst checken wat de maximale upload in onze stack is |
| `files.link` (externe MRI-viewerlink) | ❌ |
| `files.delete` (nooit voor gegenereerde documenten) | ❌ met regel "uitvoer-documenten niet verwijderbaar" |
| Vaste lijst `document_type`-waarden | 🟡 vrij veld in `additional`, ❌ enum |
| Documentcheck "verplicht voor aanmelding" (Diagnoseformulier, AFB, Anesthesieformulier, Arztbrief I) | ❌ server-side afleiden, niet in de client |

#### G-6a Recept → patiëntportaal (expliciete wens)

De Rezept Studio maakt een vergrendelde PDF ("_v2" bij een nieuwe versie). Wat het CRM moet doen:

1. ✅ File-activiteit op het traject (en/of de order) aanmaken: dit mechanisme bestaat.
2. ✅ Publiceren naar het patiëntportaal via `activity_portal_persons`. `GET patient/{id}/documents` toont het daarna.
3. ✅/🟡 `PatientNotification` maken: het mechanisme bestaat, het referentietype voor "recept" controleren.
4. ❌ Endpoint: `POST portal/trajectories/{id}/documents` met `document_type=recept`, `share_with_patient=true`.
   Idempotent op een client-key, zodat "Opnieuw versturen" geen tweede document maakt (het ontwerp eist dat).
5. ❌ Vergrendeling: een recept-activiteit is na aanmaken niet meer te wijzigen of te verwijderen.

Hetzelfde endpoint dekt ook Arztbrief en OP-bericht (zonder `share_with_patient`, of met, als dat later wordt besloten).

### G-7 Dossier-chat (team) ❌

| Ontwerp-operatie | CRM | Gap |
|---|---|---|
| `chat.list` / `counts` (berichten per dossier, ongelezen-tellingen per lijst) | notes op sales_lead | ❌ geen draad, geen telling per gebruiker |
| `chat.create` / `reply` (draad met parent) + **@mentions** → melding bij de genoemde | — | ❌ `parent_id` + mentions + melding (G-8) |
| `chat.attachment` (bijlage in bericht) | — | ❌ |
| `chat.delete` (alleen eigen bericht) | — | ❌ |
| `users.list` (namen en avatars voor mentions, geen e-mail) | — | ❌ `GET portal/users` (alleen portaalgebruikers) |

Let op: `PatientMessage` / `patient/{id}/messages` is de **patiëntchat** en blijft gescheiden. Dit is
interne communicatie tussen zorgadviseur, arts en kliniek; die mag **nooit** bij de patiënt terechtkomen.

**Advies:** `Activity type=note` uitbreiden met `parent_id` en een `mentions`-pivot. Bouw geen nieuw
berichtensysteem. Interne notities (`notes.*`) zijn dan hetzelfde type, maar zonder draad.

### G-8 Meldingen voor medewerkers ❌

| Ontwerp | CRM | Gap |
|---|---|---|
| `inbox.list` / `inbox.read` (persoonlijke meldingen: mention, antwoord, statuswissel) | `PatientNotification` alleen voor patiënten | ❌ Laravel `notifications`-tabel (database channel) voor `User` + 2 endpoints |

### G-9 Home per rol ❌

Het ontwerp verwacht **per rol één samengestelde aanroep** met een vaste vorm
(`{rol, datum, hint, taken[], perioden[], secties[]}`, zie `HOME_ROLSCHERMEN.md` §3).

| Rol | Blokken | Bron in CRM (na de gaps hierboven) |
|---|---|---|
| **Kliniek** | Planning (deze/volgende week, per behandeltype) · Operaties vandaag (tijd, kamer, documentcheck) · Nieuwe aanmeldingen (docs compleet?) · Ontslag vastleggen | G-4 + G-5 + G-6 |
| **Arts** | Te beoordelen · Vraag aan arts · Arztbrief nodig · OP-verslag nodig | G-3 (work_status) |
| **Zorgadviseur** | Beoordelingen gereed · Antwoord van de arts · Brieven klaar om te verzenden · Voorbereiding operaties · Uitgevoerde operaties · Consulten/nacontroles · Belstrip "morgen bevestigen" | G-3 + G-4 + G-5 |

Endpoints: `GET portal/home` (rol uit token) en optioneel `GET portal/home/version` (goedkope
fingerprint voor polling, max `updated_at` over de relevante tabellen). Er bestaat al een vergelijkbaar
aggregatiepatroon in `ClinicGuideController::get` en `OperationalDashboardController`; dat hergebruiken.

**Advies:** de server levert **data per blok**, de portal-frontend bepaalt teksten, kleuren en indeling.
De `toon`/`kleur`-velden uit het monday-contract hoeven niet in de API.

### G-10 Beheer 🟡

| Ontwerp | CRM | Gap |
|---|---|---|
| `admin.users` / `assignRoles` | Webkul User + Role (admin-web) + Keycloak-sync (`UserObserver`) | ✅ blijft in CRM-admin, geen API nodig |
| `admin.policy` / `savePolicy` / `schemaAudit` (veldclassificatie) | — | **niet bouwen**: dat is een monday-probleem (onbekende kolommen). In het CRM bepalen de API Resources wat zichtbaar is |
| `admin.security`, `bootstrap`, `oauthStart`, `setGuestValidation` | — | **niet bouwen**: monday-specifiek |
| Preview-als-rol (alleen lezen) | — | optioneel, laag |

---

## 5. Wat niet gebouwd hoeft te worden

Deze zaken zijn monday-specifiek en hebben geen equivalent in het CRM nodig:

- `patients.schema` / `items` / `itemsByIds` / `itemsByRelation` / `operations.schema`: "vorm-compatibele"
  routes die monday-itemvorm nadoen. Het portaal krijgt gewone JSON-resources.
- de veldclassificatie (`UNCLASSIFIED`), board shielding, gastvalidatie en de OAuth-installatielink.
- weekgroepen, slotnummers en het chunken onder de Cloudflare-grens van 10 MiB, zolang onze eigen upload-route
  die grens niet heeft (te verifiëren).
- het monday-versiestempel per bord: vervangen door `updated_at`.

---

## 6. Voorgestelde API-oppervlakte (samenvatting)

Alle routes vallen onder `api/portal`, met Keycloak-bearer voor medewerkers, een rol uit de token/User, en Policies.

```
GET    portal/me                                    rol, capabilities, kliniek
GET    portal/home[/version]                        G-9
GET    portal/users                                 G-7 (mentions)
GET    portal/trajectories                          G-2 werkvoorraad (arts: alleen toegekend, G-1a)
GET    portal/trajectories/{id}                     G-2
PATCH  portal/trajectories/{id}                     G-2 diagnose, beratung, regio, siehe chat
PUT    portal/trajectories/{id}/work-status         G-3
GET    portal/trajectories/{id}/appointments        G-4
GET    portal/trajectories/{id}/documents           G-6
POST   portal/trajectories/{id}/documents           G-6 / G-6a (incl. share_with_patient)
GET    portal/trajectories/{id}/messages            G-7
POST   portal/trajectories/{id}/messages            G-7 (parent_id, mentions)
DELETE portal/messages/{id}                         G-7
GET    portal/trajectories/{id}/notes   POST ...    G-7
GET    portal/appointments?from=&to=                G-4
GET    portal/appointments/{id}                     G-4
POST   portal/appointments                          G-4 plannen (operatie/consult/nacontrole)
PATCH  portal/appointments/{id}                     G-4 planning wijzigen
POST   portal/appointments/{id}/cancel              G-4
GET    portal/availability                          G-4 vrije capaciteit
GET    portal/appointments/{id}/clinical-record     G-5
PATCH  portal/appointments/{id}/clinical-record     G-5 (ontslag, kamer, anesthesie, medicatie)
GET    portal/appointments/{id}/documents           G-6
POST   portal/appointments/{id}/documents           G-6 (OP-bericht, AFB, ...)
GET    portal/documents/{id}/download               G-6 signed, kortlevend
POST   portal/documents/links                       G-6 externe MRI-link
DELETE portal/documents/{id}                        G-6 (niet voor uitvoer-docs)
GET    portal/notifications   POST .../read         G-8
```

≈ 27 endpoints. Patiënten en dossiers aanmaken blijft in het CRM. Dat is minder dan de helft als de ≈ 60 operaties in het ontwerp, omdat de monday-compatibele en
beheer-operaties vervallen.

---

## 7. Datamodel-wijzigingen (samenvatting)

| Wijziging | Gap |
|---|---|
| `salesleads`: `doctor_user_id` (FK users; verplicht voor Herniapoli) + backfill + CRM-rol Arts + toekennen in CRM-admin | G-1a |
| `users.clinic_id` (arts en kliniekgebruiker, één kliniek per gebruiker) | G-1a |
| `salesleads`: `region`, `diagnosis` (text), `treatment_advice` (text), `see_chat` (bool), `work_status` (enum) | G-2, G-3 |
| `order_clinical_records` (nieuwe tabel, zie G-5) | G-5 |
| `order_items`: optiestatus + `option_expires_at` | G-4 |
| `activities`: `parent_id`, `document_type` als kolom/enum (nu `additional`), lock-vlag voor uitvoer-docs | G-6, G-7 |
| `activity_mentions` (pivot user ↔ activity) | G-7 |
| `notifications` (Laravel standaard, voor `User`) | G-8 |

Al deze wijzigingen raken meerdere modules en de auth-laag. Volgens de werkafspraken is dit
**Architecture Mode**: eerst bespreken voordat er gebouwd wordt.

---

## 8. Voorgestelde volgorde

1. **G-1 + G-1a Auth, rollen en arts-toekenning**: zonder dit kan niets anders veilig open. De arts-toekenning
   in de CRM-admin kan al eerder live, los van het portaal.
2. **G-2 + G-3 Dossier + werkstatus**: daarmee kan de arts zijn hele flow doen (lezen, diagnose, status).
3. **G-6 + G-6a Documenten + recept naar patiëntportaal**: de expliciete wens; hergebruikt het bestaande portal-publish.
4. **G-4 + G-5 Planning + operatierecord**: het kliniekscherm en de planning van de zorgadviseur.
5. **G-9 Home per rol**: pas als de bronnen er zijn.
6. **G-7 + G-8 Chat + meldingen**: grootste nieuwe concept; eerst nagaan of de huidige notes voorlopig volstaan.

---

## 9. Acties / stories

**Auth, rollen en toewijzing (G-1, G-1a)**

1. Kliniek koppelen aan gebruiker (`users.clinic_id`) + beheer in CRM-admin
2. CRM-rol Arts + Keycloak-rol
3. Arts toekennen aan Herniapoli-sale (`doctor_user_id`) in CRM-admin
4. Backfill arts op bestaande Herniapoli-sales
5. Arts verplicht vanaf "Casus bij arts ter beoordeling"
6. Portaal-auth voor medewerkers (`api/portal`, Keycloak-bearer, rol uit User)
7. Zichtbaarheid per rol (query-scope + Policies)
8. `GET portal/me`

**Dossier en werkstatus (G-2, G-3)**

9. Medische velden op sale: regio, diagnose, Behandlungsberatung, Siehe Chat
10. Werkstatus als aparte kolom (10 statussen)
11. Werkvoorraad-endpoint (lijst trajecten)
12. Dossier-endpoint (detail traject)
13. Dossiervelden wijzigen met conflictcontrole
14. Werkstatus wijzigen met sluitcontrole

**Documenten (G-6, G-6a)**

15. Vaste documentsoorten (`document_type`-enum)
16. Documenten per traject en per operatie, met check aanwezig/ontbreekt
17. Document uploaden vanuit portaal (Arztbrief, OP-bericht, ...)
18. Recept opslaan, vergrendelen en delen met patiëntportaal (idempotent)
19. Kortlevende download-links voor medewerkers
20. Externe MRI-viewerlink toevoegen
21. Document verwijderen (niet voor gegenereerde documenten)
22. Grote uploads (MRI) uitzoeken

**Planning en operatierecord (G-4, G-5)**

23. Intake-datum en aankomsttijd: plek in datamodel bepalen (V8)
24. Afspraken-overzicht per periode
25. Afspraak-detail en afspraken per traject
26. Beschikbaarheid ontsluiten
27. Afspraak plannen (operatie, consult, nacontrole)
28. Status Optie met vervaldatum + automatisch vrijgeven
29. Afspraak annuleren met reden
30. Kliniek van resource moet gelijk zijn aan kliniek van arts bij boeken
31. Klinisch operatierecord (kamer, OP-tijd, ontslag, medicatie, anesthesie)
32. Operatierecord lezen/wijzigen met veldrechten per rol

**Home (G-9)**

33. Home Kliniek
34. Home Arts
35. Home Zorgadviseur
36. Home-versie voor polling

**Chat en meldingen (G-7, G-8)**

37. Dossier-chat met draad en @mentions
38. Gebruikerslijst voor mentions
39. Meldingen voor medewerkers (inbox + gelezen)

---

## 10. Open vragen

| # | Vraag | Waarom het uitmaakt |
|---|---|---|
| ~~V1~~ | ~~Patiëntbegeleider in het portaal?~~ | **Besloten: nee** |
| ~~V2~~ | ~~Scope arts en kliniek~~ | **Besloten:** arts via toekenning aan sale, kliniek via arts → kliniek (G-1a) |
| ~~V3~~ | ~~Patiënten en dossiers aanmaken in het portaal?~~ | **Besloten: blijft in het CRM** |
| V4 | Wie beheert de **operatiecapaciteit**: het portaal (ontwerp: alle rollen) of alleen de CRM-admin (Resources/Shifts)? | Bepaalt of G-4-capaciteit überhaupt een API nodig heeft |
| V5 | Eén operatie per Order, of kan één Hernia-order meerdere operaties bevatten? | Bepaalt of het klinisch record aan `orders` of `order_items` hangt |
| V6 | Welke bron is leidend voor het **diagnoseformulier**: `leads.diagnoseform_pdf_url` of het Anamnesis-formulier uit het patiëntportaal? | Er zijn nu twee paden |
| V7 | Moeten Arztbrief en OP-bericht ook naar het patiëntportaal, of alleen het recept? | Bepaalt de default van `share_with_patient` |
| V8 | Waar leven **intake-datum** en **aankomsttijd**: `orders.first_examination_at/_time` of een eigen orderregel? | Nodig voor het kliniekscherm (Home en weekplanning) |
| ~~V9~~ | ~~"CRM-integratie geparkeerd" in het monday-project~~ | **Oud besluit, vervalt** |
| ~~V10~~ | ~~Aantal artsen per sale~~ | **Besloten:** één arts per sale, verplicht, alleen Herniapoli |
| ~~V11~~ | ~~Moment van verplichting~~ | **Besloten:** verplicht bij de overgang naar "Casus bij arts ter beoordeling", niet bij het aanmaken |
| ~~V12~~ | ~~Eén of meer klinieken per arts~~ | **Besloten:** één kliniek per arts → `users.clinic_id` |
