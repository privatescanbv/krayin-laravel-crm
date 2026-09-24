# Gap-analyse: Herniapoli Monday → Clinic Portaal

**Doel:** de Monday-app die Herniapoli nu gebruikt, omzetten naar een clinic portaal in dezelfde lijn als het bestaande patient portaal.

**Bronnen (peildatum 22-09-2026):**

- Monday-app: `/Users/mark/Downloads/Herniapoli-monday-broncode-2026-09-22` (v45 Draft, ~31k regels)
- Patient/clinic portaal: `/Users/mark/workplace/privatescan/privateforms`
- CRM (bron van waarheid voor planning, orders, personen): deze repo

**Publiek:** product + development, voor een eerste inschatting van ontwikkeltijd.

---

## 1. Samenvatting

De Monday-app is een **compleet kliniek-operatiesysteem** (vier rollen, werkvoorraad, dossier, planning, operatiedag, documenten). Het clinic portaal in privateforms is een **welkomstpagina**. Het CRM heeft al de ruggengraat voor **wie wanneer waar gepland staat**, maar bijna niets voor **wat er op de operatiedag gebeurt**.

| Laag | Nu | Nodig |
|---|---|---|
| Patient portaal | Volledig (afspraken, docs, berichten, formulieren, 2FA) | n.v.t. — patroon om te kopiëren |
| Clinic portaal | Stub: login + “Welkom” | Alle kliniekschermen |
| CRM-datamodel | Clinic, Resource, Shift, Order, OrderItem, slot, Person, Anamnesis, narcoseformulier | Operatiedag-velden, documenttypes, clinic-user-koppeling, clinic-API |
| Monday | Operationele waarheid (twee borden) | Uitfaseren; data naar CRM |

**Aanbevolen architectuur:** zelfde patroon als het patient portaal.

```
Kliniekmedewerker
  → Clinic Portaal (privateforms, Blade, Keycloak-rol `clinic` / later `guide`)
    → nieuwe CRM clinic-API
      → CRM-modellen (bron van waarheid)
```

Monday is nu zowel UI als database. Die twee moeten uit elkaar. De portal presenteert; het CRM bewaart.

**Hoofdschatting (1 ontwikkelaar, inclusief tests, exclusief buffer):**

| Scope | Kalender | Toelichting |
|---|---|---|
| **A. Clinic-dag MVP** (Kliniek + Patiëntbegeleider) | **20–28 weken** | Vervangt wat Procelsio en de begeleider nu in Monday doen |
| **B. Volledige Monday-vervanging** (plus Zorgadviseur + Arts + documentgeneratie) | **42–52 weken** | Hele suite; advisor/arts horen functioneel eerder in het CRM |

Met twee ontwikkelaars die parallel API en UI doen: grofweg **0,6×** kalendertijd (niet de helft), door koppelvlakken en review.

De rest van dit document onderbouwt die range.

---

## 2. Wat er nu staat

### 2.1 Monday-app (bron)

Custom Object in monday.com. Twee borden als database:

| Bord | Inhoud |
|---|---|
| Patiëntenbord | Patiënt, Behandeltraject, Operatierecord, Interne notitie (onderscheiden via *Recordtype*) |
| Operatiebord | Afspraken (Operatie, Consult, Nacontrole) + vrije capaciteitsslots |

Frontend: vanilla JS, geen framework, ~22k regels. Gateway: Node, ~9,4k regels. Identiteit via monday-sessie; rechten per rol én per veld.

Vijf rollen:

| Rol | Wat ze doen |
|---|---|
| Zorgadviseur | Dossier, werkstatus, plannen, brieven, patiëntcontact |
| Arts | Beoordelen, diagnose, Arztbrief, OP-verslag |
| Kliniek | Operatiedag: aanmelding, anesthesie, kamer, ontslag, Procelsio-OP-bericht |
| Patiëntbegeleider | Kliniekdag-lijst, anesthesiecontrole, dagplanning-PDF |
| Beheerder | Gebruikers, rechten, veldclassificatie |

### 2.2 Patient portaal (patroon)

In privateforms, onder `/patient/*` en `patient.*.privatescan.nl`. Keycloak + 2FA. Praat met CRM via `CrmService` (`GET/POST /patient/{keycloakId}/…`).

Schermen die er staan: welkom, afspraken, documenten, berichten, profiel/wachtwoord, formulierenwizard, onboarding, 2FA.

### 2.3 Clinic portaal (stub)

Zelfde codebase, andere middleware (`portal.clinic`, rollen `employee` of `clinic`).

- Routes: `GET /clinic/` en domain `clinic.local.privatescan.nl`
- Controller: alleen `welcome()`
- View: placeholdertekst op het patient-layout
- Geen afspraken, geen dossier, geen schrijfacties
- Clinic-only gebruikers komen niet bij `/patient/forms/*` (die middleware eist `patient` of `employee`)

### 2.4 CRM (deels klaar)

Bestaat en is in gebruik:

- `Clinic` → `ClinicDepartment` → `Resource` → `Shift` → `ResourceOrderItem` (slot)
- `Person`, `Lead`, `SalesLead`, `Order`, `OrderItem`
- Hernia-pipelines (lead / sales / order)
- Anamnesis + `hernianarcose` formulier in privateforms
- Patient-API (alleen patiënt-self)
- Admin **Clinic Guide** (`/admin/clinic-guide`): read-only daglijst voor orders in “Wachten op uitvoering” — dichtstbijzijnde bestaande scherm, maar CRM-admin, niet clinic-portaal
- AFB-pakketten: **bewust overgeslagen voor Herniapoli**

Ontbreekt: clinic-API, clinic-user gekoppeld aan een kliniek, operatiedag-velden, getypeerde herniadocumenten, werkvoorraad zoals Monday die kent.

---

## 3. Scopekeuze (bepaalt de schatting)

De Monday-app is groter dan een “clinic portaal”. Twee redelijke scopes:

### Scope A — Clinic portaal in enge zin (aanbevolen eerste levering)

Wat de **kliniek** en de **patiëntbegeleider** op de dag zelf nodig hebben:

- Home Kliniek (operaties vandaag, nieuwe aanmeldingen, ontslag vastleggen)
- Home Begeleider (eerstvolgende kliniekdag, anesthesie-bakken, PDF)
- Patiëntdossier (lezen + kliniekvelden schrijven)
- Operatiedetail (voorbereiding + operatiedag + ontslag)
- Documenten bekijken/uploaden
- Narcoseformulier inzien

Zorgadviseur en arts blijven in het **CRM**. Planning/capaciteit blijft CRM-planning. Arztbrief/Rezept blijven CRM of een latere fase.

### Scope B — Monday 1-op-1 vervangen

Alles uit de suite, inclusief werkstatus-bord, arts-cockpit, weekplanner, Document Studio, Rezept Studio, interne chat, beheer.

Dat is een tweede product, niet “het clinic portaal”. Het hoort deels in CRM-admin, deels eventueel later in hetzelfde portaal als extra rollen.

**Dit document rekent Scope A als de levering, Scope B als meerwerk.**

---

## 4. Datamodel-gap

Monday modelleert vijf logische entiteiten op twee platte borden. CRM heeft relationele equivalenten voor planning, niet voor uitvoering.

### 4.1 Entiteiten mappen

| Monday | CRM nu | Gap |
|---|---|---|
| Patiënt (`name`, `firstName`, `birthdate`) | `Person` (rijker: e-mail, tel, adres, BSN, taal, Keycloak) | Geen. CRM is superieur. |
| Behandeltraject (regio, werkstatus, diagnose, advies, docs) | `SalesLead` + pipeline + `Anamnesis` + order | **Werkstatus** (werkvoorraad) ≠ sales-pipeline (patiëntreis). Diagnose/Behandlungsberatung hebben geen eigen velden (zitten in activities/AI/forms). |
| Afspraak op operatiebord | `ResourceOrderItem` op `OrderItem` (`from`/`to`) | Slot bestaat. **Afspraaktype** (Operatie/Consult/Nacontrole), **planningsstatus** (Vrij/Optie/Bevestigd/Geannuleerd), intake-datum, aankomsttijd, optie-verval, “tijd bevestigd aan patiënt” ontbreken als first-class velden. |
| Operatierecord (blijvende historie, nooit overschrijven) | Geen | **Nieuw model nodig.** Geplande slot ≠ uitgevoerde operatie. |
| Interne notitie (type: Algemeen/Consult/Intake/Nacontrole) | `Activity` type `note` | Notities bestaan, **getypeerde klinieknotitie** niet. |
| monday Updates (interne chat op dossier) | `PatientMessage` is patiënt↔organisatie; intern is e-mail/activity | Interne staf-chat zit niet in het clinic portaal. Voor Scope A: weglaten of CRM-activities tonen. |

Kernregel uit Monday die het CRM nu niet afdwingt:

> Eén patiënt, meerdere trajecten; per traject meerdere operaties. Een nieuw record mag een oud **nooit overschrijven**.

In CRM is de analogie: `Person` → meerdere `SalesLead`/`Order` → meerdere `OrderItem` + slots. Dat past. Wat ontbreekt is een **Operatierecord** dat de uitvoering bewaart los van het slot (annuleren/verzetten mag het klinische record niet wissen).

### 4.2 Velden: operatiedag (grootste gat)

Deze Monday-velden hebben **geen kolom** in het CRM. Dit is het hart van Scope A.

| Groep | Monday-velden | Voorstel CRM |
|---|---|---|
| Anesthesie | `Anesthesiestatus`, `Toelichting anesthesie`, bestanden AFB / Anesthesieformulier / Medicatielijst | Kolommen op operatierecord + status-enum (nooit stilzwijgend “akkoord”) |
| Operatiedag | Werkelijke OP-tijd, kamernummer, voorovernachting, bijzonderheden opname, zelfoplossende hechtingen | Zelfde record |
| Ontslag | Ontslagdatum, ontslagtijd, medicatie tijdens opname, medicatie meegegeven, receptvoorschrift | Zelfde record; Home Kliniek schrijft hierheen |
| Aanmelding kliniek | `Anmelden bei Procelsio?`, `Procelsio Dokumente heruntergeladen?` | Flags op order of record (geen Procelsio-API in Monday; ook hier flags houden) |
| Behandeling | Behandelingstype / `Termine` | Deels `OrderItem.product` / `PartnerProduct`; extra vrije typering mogelijk nodig |
| Planning extra | Intake-datum, `Ankunftszeit`, “Bevestigd klant?”, slotnummer, optie vervalt, reden annulering | Uitbreiding op slot of order-item, niet op het klinische record |

**Niet bouwen als nieuwe entiteit:** kamer als Resource mag later; MVP is een tekst/nummer op het record (zoals Monday `Zimmer nr?`).

### 4.3 Documenten

Monday heeft **getypeerde bestandskolommen**. CRM heeft losse `Activity` files met optioneel `additional.document_type` (`report`, `order_confirmation`). AFB-PDF’s zijn een apart pad en gelden niet voor hernia.

| Monday-document | Verplicht voor aanmelden? | CRM nu |
|---|---|---|
| Diagnoseformulier | ja | Lead `diagnoseform_pdf_url` / forms; niet als clinic-zichtbaar typed doc |
| AFB | ja | AFB-dispatch **niet** voor Herniapoli |
| Anesthesieformulier | ja | Narcoseformulier in privateforms (`hernianarcose`) — link bestaat via `anamnesis_gvl_forms` |
| Arztbrief I | ja | Niet als typed clinic-doc |
| 1e MRT / CT | nee | Patient-portal files, untyped |
| OP-bericht (arts) | nee (na de ingreep) | Pipeline-stage “wachten op operatieverslag”; geen typed file |
| Procelsio OP-bericht | nee | Ontbreekt |
| Medicatielijst | nee | Ontbreekt |
| Arztbrief II, Recept, MRT-Befund | — | Ontbreekt als type |

Nodig: een **documenttaxonomy** (enum) plus koppeling aan SalesLead/Order/Operatierecord, hergebruik van bestaande file-storage (`activity_files` of een dunne `clinical_documents`-tabel). Zonder types kan de Home-check “mag deze patiënt aangemeld worden?” niet.

Gegenereerde documenten in Monday (Arztbrief I, Rezept) zijn Scope B. In Scope A: uploaden/inzien is genoeg; genereren blijft bij de zorgadviseur in CRM.

### 4.4 Werkstatus vs pipeline

Monday-werkstatus (wie is aan zet):

`Casus bij arts ter beoordeling` · `Vraag aan arts` · `Artzbrief nodig` · `OP-verslag nodig` · `Beoordeling gereed` · `Vraag beantwoord` · `Arztbrief verzendklaar` · `OP-verslag verzendklaar` · `Wachten` · `Afgerond`

CRM hernia-sales-pipeline (patiëntreis): o.a. casus bij arts, beoordeling gereed, wachten op verzekeraar, behandeling gepland, nazorg, …

Die twee overlappen in naam, niet in betekenis. Scope A heeft de werkstatus-bakken **niet** nodig (dat is advisor/arts). Scope B: óf sales-stages herijken, óf een apart `work_status`-veld op `SalesLead`. Niet forceren tot één enum.

### 4.5 Gebruikers en scoping

| Behoefte | Nu |
|---|---|
| Keycloak-rol `clinic` | Bestaat; opent alleen de stub |
| Gebruiker hoort bij **één kliniek** (Procelsio) | Ontbreekt. CRM-users hebben geen `clinic_id`. Order heeft alleen `clinic_coordinator_user_id` |
| Rol `guide` (patiëntbegeleider) | Ontbreekt in Keycloak/privateforms (wel in Monday) |
| Veldrechten per categorie (medisch/kliniek/financieel) | Ontbreekt in het portaal; CRM heeft admin-permissions, niet dit model |
| Financieel verbergen voor kliniek | CRM toont prijzen in Clinic Guide JSON (`total_price`) — mag niet naar het clinic portaal |

MVP: Keycloak-rol + mapping user→clinic (pivot). Fine-grained veldmatrix zoals Monday kan later; tot die tijd: clinic ziet geen `total_price`, geen OP-techniek, geen inkoop.

### 4.6 Voorstel nieuwe CRM-modellen (Scope A)

Minimaal:

1. **`operation_records`** (1 per uitgevoerde/geplande ingreep, gekoppeld aan `order_item_id` + optioneel `resource_orderitem_id`)
   - anesthesie-status + toelichting
   - overnight, actual_op_time, room, remarks, sutures
   - discharge_date, discharge_time
   - meds_admission, meds_given, prescription (tekst)
   - clinic_registered_at, clinic_docs_received_at
   - treatment_type (als productnaam niet volstaat)
2. **`clinical_documents`** of typed `Activity.additional.document_type`
   - diagnoseform, afb, anesthesia_form, arztbrief_1, mri_1, ct, op_report, procelsio_op_report, medication_list, …
3. **`clinic_user` pivot** (`user_id` of `keycloak_user_id` → `clinic_id`, rol `clinic`/`guide`)
4. Optioneel op `order_items` / slots: `appointment_type`, `planning_status`, `intake_at`, `arrival_time`, `patient_confirmed_at`, `option_expires_at`, `cancel_reason`

Niet in Scope A: intern chat-model, werkstatus-veld, documentgenerator-assets, monday-achtige capaciteitsslots “Vrij” (CRM heeft al shifts).

### 4.7 Wat we expres niet 1-op-1 overnemen

- Twee borden met gedupliceerde velden (anesthesie staat in Monday op afspraak **en** record). In CRM: **één** schrijfplek (het operatierecord); het slot is alleen planning.
- Kolommen zoeken op titel. CRM heeft schema.
- DIRECT-modus / monday column-IDs in de client.
- 10 MiB Cloudflare-uploadlimiet van Monday Code — CRM/S3 kan groter, wel een eigen limiet zetten.

---

## 5. Scherm-gap

### 5.1 Patient portaal vs clinic portaal (patroon)

| Patient portaal | Clinic-equivalent | Status |
|---|---|---|
| Login Keycloak + 2FA | Zelfde | Auth staat; rolcheck staat |
| Welkom met tegels/counts | Clinic Home | Alleen placeholder |
| Afsprakenlijst (eigen) | Daglijst alle patiënten van de kliniek | Ontbreekt |
| Documenten (eigen) | Dossierdocumenten van geplande patiënten | Ontbreekt |
| Berichten | Intern of naar Herniapoli — productkeuze | Ontbreekt; patient-messages zijn de verkeerde richting |
| Formulieren invullen | Narcose/diagnose **inzien** (invullen is patiënt) | Clinic-role komt de form-routes niet in |
| Profiel | Clinic-profiel overbodig in MVP | — |

Layout: Blade + Tailwind v4 + Bootstrap 5.3. Clinic-views moeten een **eigen navigatie** krijgen; de stub hergebruikt nu `layouts.admin` van de patiënt.

### 5.2 Monday-schermen → Scope A

| Monday-scherm | Rol | Clinic portaal | Inschatting |
|---|---|---|---|
| Home Kliniek | clinic | **Nieuw** — drie blokken + ontslagformulier | Groot; logica zit in `home-clinic.js` / gateway |
| Home Begeleider | guide | **Nieuw** — kliniekdag + 3 anesthesie-bakken + PDF | Groot; `home-guide.js` + `guide-pdf.js` |
| Patiëntenbord (8 werkstatus-bakken) | advisor | **Niet in Scope A** (CRM) | — |
| Patiëntpopup Overzicht | allen | Dossier-kop: patiënt, regio, diagnose, advies, narcose-status | Middel; grotendeels read |
| Patiëntpopup Planning | allen | Lijst afspraken + huidige operatierecord | Middel |
| Patiëntpopup Chat | allen | Scope A: weglaten of “enkel lezen CRM-notes” | Klein als skip |
| Patiëntpopup Bestanden | allen | Typed documentenlijst + upload | Middel |
| Operaties weekrooster + capaciteit | advisor/clinic | Scope A: **niet** (CRM-planning). Wel: “vandaag” uit Home | — |
| Operatiedetail — Voorbereiding | clinic/guide | Anesthesie + verplichte docs | Groot |
| Operatiedetail — Operatiedag & ontslag | clinic | Kamer, OP-tijd, hechtingen, medicatie, ontslag, OP-berichten | Groot |
| Ontslag vanuit Home | clinic | Zelfde write-pad als detail | Klein als detail er is |
| Document Studio / Rezept | advisor/arts | Scope B | — |
| Beheer rollen/velden | admin | Later; tot die tijd Keycloak + vast rechtenset | Klein in A |

### 5.3 Home Kliniek — functioneel contract (niet onderhandelen)

Overgenomen uit `docs/HOME_ROLSCHERMEN.md`, dit moet het portaal kunnen:

1. **Operaties vandaag** — bevestigde operaties op `datum`, met type en tijd.
2. **Nieuwe aanmeldingen** — documentcheck (verplicht: diagnoseformulier, AFB, anesthesieformulier, Arztbrief I; optioneel MRT/CT). Flags: aangemeld bij Procelsio / documenten ontvangen.
3. **Ontslag vastleggen** — datum verplicht, tijd + medicatie mee; schrijft het operatierecord; zonder record geen formulier maar uitleg.

### 5.4 Home Begeleider — functioneel contract

1. Eerstvolgende kliniekdag (geen vaste weekdag): Operatie-intake, Consult of Nacontrole.
2. Anesthesie in drie bakken: *Te controleren* · *Formulier nog niet ontvangen* · *Gecontroleerd*. Ontbrekend formulier ≠ “nog niet gecontroleerd”.
3. PDF-dagplanning (A4), client-side is acceptabel (zoals nu jsPDF).
4. Begeleider schrijft **niet** vanuit Home; anesthesiestatus via dossier.

### 5.5 Bestaande CRM Clinic Guide

`/admin/clinic-guide` is een Vue-daglijst: patiënt, tijd, order items, GVL-links, AFB-PDF (MRI). Read-only, toont `total_price`, geen hernia-docs, geen schrijven.

**Hergebruik:** query-idee (orders op datum via slots) als startpunt voor de clinic-API. **Niet** het scherm zelf naar het portaal tillen — verkeerde auth, verkeerde data, verkeerde rol.

### 5.6 Taal

Monday: zorgadviseur NL; arts en kliniek **Duits**. Patient portaal: NL/EN/DE via voorkeur. Clinic portaal moet **Duits als default** voor `clinic`, NL voor `guide` (Nederlandse begeleider). i18n-laag in privateforms bestaat; clinic-copy moet nog geschreven.

---

## 6. API-gap

Patient-API (`routes/api.php`, `patient.self`) is het voorbeeld. Voor clinic ontbreekt het complete blok.

Benodigde CRM-endpoints (schets):

| Method | Pad | Doel |
|---|---|---|
| GET | `/clinic/me` | Kliniek, rol, naam |
| GET | `/clinic/home` | Rol-dashboard payload (clinic of guide), `?date=` |
| GET | `/clinic/day` | Patiënten/operaties op datum |
| GET | `/clinic/patients/{person}` | Dossier samenvatting (scoped tot deze kliniek) |
| GET | `/clinic/operations/{record}` | Operatiedetail |
| PATCH | `/clinic/operations/{record}` | Kliniekvelden (anesthesie, kamer, ontslag, flags) |
| GET/POST | `/clinic/operations/{record}/documents` | Typed files |
| GET | `/clinic/operations/{record}/documents/{id}/download` | Stream |
| GET | `/clinic/day.pdf` | Optioneel server-side PDF; anders portal-side |

Autorisatie: Keycloak bearer of API-key + portal zoals patient; **altijd filteren op `clinic_id`**. Geen financiële velden. Audit log op elke write (CRM heeft `HasAuditTrail`).

Privateforms krijgt een `ClinicCrmService` naast `CrmService` — niet de patient-endpoints hergebruiken (die zijn self-scoped op één persoon).

---

## 7. Migratie vanuit Monday (apart van bouwen)

De app draait op echte borden (`5104001891`, `5104299651`). Er is al een `tools/patient-migration/` in de Monday-repo.

Migratie is geen UI-werk, wel tijd:

- Patiënten → `Person` (dedupe op naam+geboortedatum; CRM kan rijker zijn)
- Trajecten → `SalesLead` / bestaande hernia-orders matchen
- Afspraken → `ResourceOrderItem` (Procelsio-resources staan al in de seeder)
- Operatierecords + bestanden → nieuwe tabellen + storage
- Historie niet overschrijven

Grove extra: **3–6 weken** als eenmalige migratie + dry-run op kopie, inclusief bestandskoppelingen. Kan nà MVP-schermen (eerst nieuw-in, dan historie), maar live cutover zonder historie is voor de kliniek onacceptabel.

Risico: Draft en Live van Monday delen nu dezelfde productieborden. Migratie moet read-only tegen productie, schrijven alleen naar CRM.

---

## 8. Ontwikkeltijd

Aannames:

- 1 ontwikkelaar die Laravel/CRM/privateforms kent
- Pest-tests bij elk pad (zoals patient portal)
- Geen pixel-perfect Monday-kloon; wel dezelfde **beslissingen en checks**
- Geen Procelsio-koppeling (flags blijven flags)
- Review/QA/product afstemming niet meegerekend (~20% bovenop)
- Browserverificatie van de portal-schermen zit in de schatting

### 8.1 Scope A — Clinic-dag MVP

| Fase | Werk | Weeken |
|---|---|---|
| 0 | Besluiten: mapping OrderItem↔operatierecord, documenttypes, Keycloak-rol `guide`, Duits/NL | 1 |
| 1 | CRM-datamodel + factories + migraties + policies | 3–4 |
| 2 | CRM clinic-API + scoping + audit + feature tests | 3–4 |
| 3 | Portal: shell, nav, auth, clinic-koppeling, i18n-skelet | 1–1,5 |
| 4 | Home Kliniek + ontslag schrijven | 2–3 |
| 5 | Home Begeleider + dag-PDF | 2 |
| 6 | Dossier + operatiedetail (voorbereiding + dag + ontslag) | 4–5 |
| 7 | Documenten typed upload/download + narcose-inzage | 1,5–2 |
| 8 | Hardening: lege dagen, ontbrekend record, rechten, DE-copy, E2E-smoker | 2 |
| **Som bouwen** | | **19,5–24,5** |
| 9 | Datamigratie Monday → CRM (bestanden inbegrepen) | 3–6 |
| **Totaal A** | | **22–30 weken** |

Afgerond voor planning: **5–7 maanden** één FTE, of **3–4 maanden** twee FTE (API + UI parallel vanaf fase 2).

Ijkpunt: het bestaande patient portaal (welkom, afspraken, docs, berichten, profiel, forms-integratie, 2FA) is kleiner dan alleen fase 4–7. De Monday-gateway voor Home+operatie alleen al is ~duizenden regels afleidingslogica (documentchecks, anesthesie-labels, “nooit stilzwijgend akkoord”). Die logica moet opnieuw, tegen CRM in plaats van monday-kolommen — niet kopiëren.

### 8.2 Scope B — meerwerk bovenop A

| Blok | Weeken | Waar het thuishoort |
|---|---|---|
| Zorgadviseur-Home + werkstatus-bord | 5–7 | CRM-admin, niet clinic portaal |
| Arts-Home | 2 | CRM |
| Weekplanner + capaciteit (monday-pariteit) | 3–4 | CRM-planning uitbreiden (basis bestaat) |
| Document Studio Arztbrief I | 4–6 | CRM of losse generator; assets zitten in Monday |
| Rezept Studio | 2–3 | idem |
| Interne dossier-chat | 3 | nieuw; patient-messages niet hergebruiken |
| Beheer veldmatrix à la Monday | 2 | waarschijnlijk overkill; CRM-permissions |
| **Som B** | **21–27** | |
| **Totaal A+B** | **43–57 weken** | ~10–14 maanden 1 FTE |

### 8.3 Wat je in 6–8 weken wél kunt laten zien

Een **vertical slice**, geen productie-cutover:

- Operatierecord-tabel + 5 velden
- `GET /clinic/home` voor één dag, één kliniek
- Portal-Home Kliniek read-only tegen testdata
- Eén write: ontslagdatum

Dat bewijst het patroon (portal → CRM-API → model) voordat de rest van de velden en migratie start.

---

## 9. Risico’s

| Risico | Impact | Mitigatie |
|---|---|---|
| Scope A en B door elkaar halen | Schatting ×2 | Eerst clinic-dag; advisor/arts in CRM houden |
| Werkstatus = pipeline forceren | Verkeerde bakken, kapotte CRM-funnel | Apart houden |
| Clinic Guide hergebruiken inclusief prijs | AVG + verkeerde UX | Nieuwe API, geen `total_price` |
| Geen user→clinic mapping | Elke clinic-user ziet alle klinieken | Pivot vóór eerste scherm |
| Anesthesie-labels 1-op-1 uit Monday | Onbekende labels werden “onbekend”, nooit akkoord | Zelfde veilige mapping in CRM-enum |
| Bestanden alleen in monday | Cutover zonder historie | Migratiepad in fase 9, niet “later ooit” |
| Clinic-PDF-variant van formulieren onbereikbaar voor rol `clinic` | Begeleider/kliniek kan narcose niet openen | Form-routes onder `portal.clinic` hangen |
| Twee waarheden tijdens overgang | Dubbel bijhouden | Periode: CRM schrijft, Monday read-only of uit |

---

## 10. Open vragen (voor fase 0)

1. Is de eerste levering **alleen Kliniek + Begeleider** (A), of moet de zorgadviseur ook uit Monday?
2. Mag planning in het **CRM-admin** blijven (bestaande resource-planning)?
3. Moet Keycloak een rol `guide` krijgen, of valt de begeleider onder `employee` met extra recht?
4. Eén kliniek (Procelsio) of multi-clinic vanaf dag 1?
5. Moeten historische Monday-bestanden mee in de eerste go-live?
6. Blijft interne chat een must-have, of volstaat e-mail + CRM-notes?
7. Duits als enige clinic-taal, of omschakelbaar?

---

## 11. Aanbevolen volgorde

1. Scope A vastzetten met stakeholders (kliniek + begeleider).
2. CRM-operatierecord + documenttypes ontwerpen (één schrijfplek).
3. Clinic-API + portal vertical slice (Home read-only + ontslag write).
4. Rest van de dag-schermen.
5. Migratie + paralleldraaien.
6. Monday-app uitzetten voor clinic/guide.
7. Pas daarna Scope B, bij voorkeur in CRM in plaats van in het clinic portaal.

---

## Bijlage A — Monday-velden (logisch register)

Uit `shared/resource-map.js`. Dit is de checklist voor het CRM-schema.

**Patiënt:** name, firstName, birthdate  

**Traject:** region, status (Werkstatus), seeChat, number, diagnosis, treatmentAdvice  

**Docs op traject:** diagnoseformulier, mrt1–3, ct, mrtBefund, arztbrief1–2, recept, opBericht, procelsioOp  

**Afspraak:** type, status, time, slot, optionExpiry, cancelReason, opDate, opTime, intakeDate, arrival, treatment, overnight, confirmed, clinicRegistered, clinicDocs, anesthesia, anesthesiaNote, anesthesiaForm, afb, medicationList, room, remarks, sutures, dischargeDate/Time, medsGiven, medsAdmission, prescription  

**Operatierecord:** treatmentType, anesthesia*, overnight, opTime, room, remarks, sutures, prescription, meds*, discharge*, afb, anesthesiaForm, medicationList, course, opReport, procelsioReport, technique (financieel — niet naar clinic)  

**Notitie:** text, type, author

## Bijlage B — Relevante paden

| Wat | Pad |
|---|---|
| Monday resource map | `Herniapoli-monday-broncode…/shared/resource-map.js` |
| Monday rollen | `…/shared/access-model.js` |
| Monday Home-contract | `…/docs/HOME_ROLSCHERMEN.md` |
| Clinic portal stub | `privateforms/app/Http/Controllers/ClinicPortalController.php` |
| Patient CRM-client | `privateforms/app/Services/CrmService.php` |
| Patient API | `krayin-laravel-crm/routes/api.php` (`patient/{id}`) |
| Clinic Guide | `app/Http/Controllers/Admin/ClinicGuideController.php` |
| Planning | `app/Http/Controllers/Admin/Planning/` |
| Clinic / Resource / Shift | `app/Models/{Clinic,Resource,Shift,Order,OrderItem,ResourceOrderItem}.php` |
