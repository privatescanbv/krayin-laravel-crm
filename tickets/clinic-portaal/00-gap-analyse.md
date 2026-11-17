# Gap-analyse v2: kliniekportaal — rol Arts, tab Patiënten

- **Status:** analyse (2026-11-17). Vervangt voor deze scope de brede
  [kliniekportaal-api-gap-analyse](../kliniekportaal-api-gap-analyse.md) (v1, 2026-10-29). Tickets volgen in deze map.
- **Bron van het doel:** Monday-app "Herniapoli App" (custom object 5104289904), rol **Arts**, tab **Patienten**:
  het kanbanbord en de patiëntkaart die opent bij een klik op een kaart (screenshots 2026-11-17).
  De `design`-map in de repo's is hier niet gebruikt.
- **Huidige stand gelezen in:** CRM (`routes/api/clinic.php`, `ClinicSalesLeadController`, `ClinicSalesLead*Resource`,
  `SalesLead`, `ApiKeyAuth`, lokale database) en `clinic-portal` (`SalesController`, `ClinicCrmClient`,
  `resources/views/sales/*`, `tickets/canban`, `tickets/patientkaart`).
- **Alleen CRM-werk** in de tickets van deze map; het portaal bouwt erop.

## 1. Scope

**Wel:** wat een arts ziet en doet op het Patiëntenbord en in de patiëntkaart (tabs Übersicht, Termine, Chat,
Dateien).

**Niet:** tabs Home en Operationen, rollen Kliniek en Zorgadviseur, "Neuer Patient" en "Neue Patientenakte"
(aanmaken blijft in het CRM, besloten in v1), de preview-modus "Vorschau: Arzt" (Monday-beheer). De open vragen
V4–V8 uit v1 zijn uitgesteld; ze raken deze scope niet.

## 2. Wat er al staat

| Onderdeel | CRM | Portaal |
|---|---|---|
| Inloggen arts via Keycloak, bearer naar CRM | `ApiKeyAuth::allowsKeycloakBearerFallback()` voor `api/clinic/*` (naw, password, sales); `patient.self` dwingt `sub` = route-id | `keycloak.session` + `realm.role:clinic` |
| Arts als gebruiker met rol | rol "Arts" (`RoleCode::ARTS`, role id 4); `salesleads.doctor_user_id` met keuzelijst en audit in de CRM-admin (optioneel, nog niet verplicht) | — |
| Bereik: arts ziet alleen eigen sales | `SalesLead::scopeForPortalDoctor()` + stagezichtbaarheid per rol (`lead_pipeline_stage_roles`, nu leeg = alles) | — |
| Kolommen bord | `GET clinic/{id}/sales-stages` (Hernia-salespipeline) | bord met kolom per stage |
| Kaarten | `GET clinic/{id}/sales-leads?stage_id=`: naam, geboortedatum per persoon, `stage_entered_at` | naam, geboortedatum, dagen in status ("T") |
| Kaart verslepen / status wijzigen | `PUT clinic/{id}/sales-leads/{saleId}/stage` | slepen en toetsenbord |
| Patiëntkaart | `GET clinic/{id}/sales-leads/{saleId}`: personen, stage, arts, `description`, `assessment_outcome`, `additional_research_required` | overlay + losse pagina, tab Overzicht |
| Interne notities | `GET/POST clinic/{id}/sales-leads/{saleId}/notes` | lezen + toevoegen |

## 3. Gaps

Legenda: ✅ aanwezig · 🟡 deels · ❌ ontbreekt

### 3.1 Bord

| Element (Monday) | Stand | Gap |
|---|---|---|
| Kolommen: Fall zur ärztlichen Beurteilung, Beurteilung abgeschlossen, Frage an den Arzt, Frage beantwortet, Arztbrief erforderlich, Arztbrief versandbereit, … | 🟡 | Kolommen = salesfases met rechten per rol (zie 3.3). Alleen de eerste twee bestaan al als fase; de rest komt erbij, en het rechtenbeheer per fase ontbreekt nog. |
| Zoeken op voor- of achternaam | ❌ | `?q=` op `sales-leads` (persoonsnaam, binnen artsbereik) |
| Filter "Wartet" en "Abgeschlossen" met aantallen | ❌ | filter + tellingen per filter in één aanroep; "Abgeschlossen" = afgeronde fases, "Wartet" nog te definiëren (3.3) |
| Kaart: naam, geboortedatum | ✅ | — |
| Kaart: dagen in status ("43 T") | ✅ | `stage_entered_at` (tijd in de huidige fase) |
| Kaart: regio (LWS/HWS) | ❌ | veld op sale |
| Kaart: aantal chatberichten | ❌ | volgt uit chat (3.2) |
| Kaart: diagnose (regel 1) en Behandlungsberatung (regel 2) | ❌ | velden op sale |
| Kaart: "Historie: HWS" (eerdere sale in andere regio) | ❌ | afleidbaar uit andere sales van dezelfde persoon, binnen artsbereik |

### 3.2 Patiëntkaart

| Element (Monday) | Stand | Gap |
|---|---|---|
| Kop: naam, geboortedatum | ✅ | — |
| Kop: regio-pil + status-pil | 🟡 | regio ontbreekt; status = salesfase ✅ |
| Statusknop (dropdown) | ✅ | stagewissel bestaat; keuzelijst bevat alleen fases waar de rol rechten op heeft |
| Schakelaar "Siehe Chat" | ❌ | boolean op sale + wijzigen |
| Tab Übersicht → Persönliche Daten (voornaam, achternaam, geboortedatum, leeftijd) | ✅ lezen | knop "Bearbeiten": persoonsgegevens wijzigen vanuit het portaal ontbreekt |
| Tab Übersicht → Interne Notizen (+ Notiz) | ✅ | — |
| Tab Übersicht → Diagnose (Kopieren, Bearbeiten) | ❌ | veld op sale + wijzigen (kopiëren is portaalkant) |
| Tab Übersicht → Behandlungsberatung (Kopieren, Bearbeiten) | ❌ | idem. Let op: `assessment_outcome` ("Uitkomst beoordeling") is een **code**, geen vrije tekst; het huidige portaal toont die. Behandlungsberatung is een nieuw tekstveld; of de uitkomstcode blijft, is een keuze |
| Tab Übersicht → Letzte Nachrichten (+ "Zum Chat", "eigener Gesprächsverlauf" per regio) | ❌ | volgt uit chat |
| Tab Termine | ❌ | afspraken van de sale; gedeelde basis in [portaal/02](../portaal/02-appointment-detail.md) |
| Tab Chat (met aantal) | ❌ | teamchat per sale: berichten lezen en plaatsen, aantal. Staat los van interne notities en van de patiëntchat (`PatientMessage`) |
| Tab Dateien (met aantal) | ❌ | documenten van de sale met type en download; gedeelde basis in [portaal/01](../portaal/01-document-type.md) (documenttypes, met `show_in_clinic_portal`) |

### 3.3 Werkstatus = salesfase, met rechten per rol (besloten 2026-11-17)

**Besluit:** er komt geen aparte werkstatus. De Monday-kolommen zijn **Hernia-salesfases**. De API levert de
salesfases als werkstatus, maar alleen de fases waar de rol van de gebruiker rechten op heeft. Een sale in een
fase zonder rechten is voor die gebruiker niet zichtbaar in het kliniekportaal (niet op het bord, niet als detail,
niet via een ander endpoint).

Rechten per fase zijn in te stellen in de CRM-admin, per rol: **Arts** en **Kliniek medewerker**. Een migratie
geeft initieel beide rollen rechten op alle Hernia-salesfases.

**Wat er al is en wat er moet veranderen:**

| Onderdeel | Nu | Gap |
|---|---|---|
| Koppeltabel fase ↔ rol | `lead_pipeline_stage_roles` bestaat (2026-11-05), leeg | ✅ tabel; ❌ vulling via migratie |
| Betekenis van "geen rijen" | `Stage::scopeVisibleForRole()`: geen rijen = zichtbaar voor **alle** rollen | ❌ omdraaien naar **expliciet**: alleen zichtbaar als er een rij voor de rol is. Anders wordt een fase waarvan de beheerder beide vinkjes uitzet weer voor iedereen zichtbaar |
| Beheer | alleen via de database | ❌ in de CRM-admin per fase aanvinken welke rollen hem zien (bij voorkeur in de bestaande pipelinefase-instellingen) |
| API levert alleen toegestane fases | `GET sales-stages` gebruikt `visibleForRole()` | ✅ (gaat mee met de nieuwe betekenis) |
| Sales buiten toegestane fases onzichtbaar | `scopeForPortalDoctor()` filtert op toegestane fases | ✅ voor de arts; geldt ook voor stagewissel, notities en detail. Elk nieuw endpoint moet dezelfde scope gebruiken |
| Stagewissel naar fase zonder rechten | `updateStage` zoekt de doelfase met `visibleForRole()` | ✅ wordt al geweigerd |
| Kliniek medewerker | rol bestaat (`RoleCode::KLINIEK_MEDEWERKER`, role id 5); geen portaalscope | ❌ buiten deze scope (alleen Arts), maar de rechten per fase worden nu al voor beide rollen ingesteld |

**Ontbrekende fases.** De Monday-kolommen die nog geen Hernia-salesfase zijn, worden als fase toegevoegd. Huidige
fases (lokaal) tegenover Monday:

| CRM-fase | Monday-kolom |
|---|---|
| Casus bij arts ter beoordeling | Fall zur ärztlichen Beurteilung |
| Beoordeling gereed | Beurteilung abgeschlossen |
| — (nieuw) | Frage an den Arzt |
| — (nieuw) | Frage beantwortet |
| — (nieuw) | Arztbrief erforderlich |
| — (nieuw) | Arztbrief versandbereit |
| — (nieuw, te bevestigen) | verdere kolommen rechts van het screenshot (bijv. OP-Bericht erforderlich / versandbereit) |
| Onderzoek via Privatescan, Patient bedenktijd, Gepland voor aanvullend onderzoek, Wachten op zorgverzekeraar, Behandeling bevestigen, Behandeling gepland, 1e/2e Nazorg, Gepland voor fysieke nacontrole | geen eigen kolom op Monday; met de rechten per rol uit te zetten voor de arts, of zichtbaar laten |
| Met succes / Niet succesvol afgerond | "Abgeschlossen" (filter) |

Aandachtspunten:
- Nieuwe fases vallen tussen bestaande fases (`sort_order`). De fasecodes zijn de sleutel voor code en analytics;
  de analytics-sync (`003_sync_procedure.sql`, genoemd in v1) en rapportages op fase controleren vóór de migratie.
- Nieuwe fases krijgen in de migratie ook rechten voor beide rollen.
- De filter "Wartet" heeft dan geen eigen bron meer. Te bepalen welke fases onder "Wartet" vallen (bijv. een
  vlag per fase), of "Wartet" laten vallen.

## 4. Datamodel (CRM)

| Wijziging | Voor |
|---|---|
| nieuwe Hernia-salesfases voor de ontbrekende Monday-kolommen (3.3) | kolommen |
| `lead_pipeline_stage_roles` vullen (alle Hernia-salesfases × Arts en Kliniek medewerker) + betekenis "geen rij = geen toegang" | rechten per rol |
| `salesleads.region` (LWS / HWS, nullable) | kaart, kop, "Historie" |
| `salesleads.diagnosis` (text, nullable) | kaart, Übersicht |
| `salesleads.treatment_advice` (text, nullable) | kaart, Übersicht |
| `salesleads.see_chat` (bool) | schakelaar |
| teamchat per sale (advies v1: `Activity type=note` met een eigen soort/vlag, of eigen tabel) | Chat, aantallen, Letzte Nachrichten |
| documenttypes ([portaal/01](../portaal/01-document-type.md)) | Dateien |

Alle velden ook zichtbaar en bewerkbaar in de CRM-admin (sale bewerken), met audit via `HasAuditTrail`.

## 5. API (CRM, uitbreiding van de bestaande `clinic/{keycloakUserId}/…`-routes)

De huidige routevorm blijft; de verhuizing naar `portal/*` uit v1 is geen onderdeel van deze scope.

```
GET   sales-stages                 bestaat; alleen fases met rechten voor de rol (= werkstatussen)
GET   sales-leads?q=&filter=waiting|closed   + tellingen (meta)
GET   sales-leads/{saleId}         + region, diagnosis, treatment_advice, see_chat, history[], counts{chat, files, appointments}
PATCH sales-leads/{saleId}         diagnosis, treatment_advice, see_chat (conflictcontrole op updated_at)
PUT   sales-leads/{saleId}/stage   bestaat; weigert een doelfase zonder rechten
PATCH sales-leads/{saleId}/persons/{personId}   voornaam, achternaam, geboortedatum (als de arts dit mag)
GET   sales-leads/{saleId}/appointments          (Resource uit portaal/02)
GET   sales-leads/{saleId}/documents             (+ download, alleen types met show_in_clinic_portal)
GET   sales-leads/{saleId}/messages   POST …     teamchat
```

Elk nieuw endpoint gebruikt `forPortalDoctor()`; de bearer-whitelist in `ApiKeyAuth` groeit mee (bij voorkeur
één regel voor `api/clinic/*/sales-leads/*` in plaats van losse paden).

## 6. Tickets

| # | Ticket | Afhankelijk van | Grootte |
|---|---|---|---|
| 01 | [Rechten per salesfase per rol](01-rechten-per-salesfase.md) | – | M |
| 02 | [Regio, diagnose en Behandlungsberatung op de sale](02-medische-velden-op-sale.md) | – | M |
| 03 | [Diagnose en Behandlungsberatung wijzigen vanuit het portaal](03-diagnose-en-advies-wijzigen.md) | 02 | S |
| 04 | [Zoeken op naam en filter "Abgeschlossen"](04-zoeken-en-afgerond.md) | 01 | S |
| 05 | [Tab Termine](05-tab-termine.md) | [portaal/02](../portaal/02-appointment-detail.md) | S |
| 06 | [Tab Dateien](06-tab-dateien.md) | [portaal/01](../portaal/01-document-type.md) | M |
| 07 | [Teamchat per sale (basis)](07-teamchat.md) | – | M |
| 08 | ["Historie": eerdere sale in een andere regio](08-historie-regio.md) | 02 | S |

Nog geen ticket (wacht op antwoorden in §7): ontbrekende Monday-kolommen als salesfases, filter "Wartet",
"Siehe Chat", persoonsgegevens wijzigen, uitbreidingen van de chat.

## 7. Vragen voor deze scope

- ~~Werkstatus als aparte kolom of als salesfases?~~ **Besloten:** salesfases met rechten per rol (3.3).
- Welke Monday-kolommen precies (ook de kolommen rechts van het screenshot), en waar in de volgorde?
- Wat is "Wartet": welke fases vallen eronder, of vervalt het filter?
- Mag de arts persoonsgegevens wijzigen ("Bearbeiten"), of alleen lezen?
- Blijft "Uitkomst beoordeling" (`assessment_outcome`) naast de nieuwe Behandlungsberatung bestaan, op kaart en kaartdetail?
- Wat betekent "Siehe Chat" functioneel (alleen een markering, of verandert het iets in het bord of de chat)?
