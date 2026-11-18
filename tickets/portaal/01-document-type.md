# 01 — Documenttypes: zelf te beheren collectie + beheerscherm (gedeeld: kliniek- en patiëntportaal)

**Trello:** https://trello.com/c/havMoA0Q
**Trello-label:** `NOT_READY`

**Afhankelijk van:** –  ·  **Grootte:** L  ·  **Prio:** hoog  ·  **Bron:** api-voorstel §5, gap-analyse G-6

Gedeeld door beide portalen: één tabel, één beheerscherm. Voorrang boven de andere tickets, want
[patient-portaal/08](../patient-portaal/08-documents-seen-and-filters.md),
[09](../patient-portaal/09-patient-upload.md) en de documenttickets van het kliniekportaal (G-6, G-6a in de
[gap-analyse](../kliniekportaal-api-gap-analyse.md)) hangen eraan.

## Doel

Een document heeft een **type** dat de beheerder zelf kan aanmaken en wijzigen, zonder deploy. Per type is
vastgelegd of het zichtbaar is in het kliniekportaal en/of in het patiëntportaal. Zo kan er een type bestaan
dat nooit bij de patiënt terechtkomt (bijv. een intern verslag). Er is geen categorie-laag en geen enum: het
portaal filtert en groepeert zelf op één of meer types.

## Datamodel

Tabel `document_types` (model `App\Models\DocumentType`):

| Kolom | Betekenis |
|---|---|
| `id` | |
| `name` | toonbare naam (Nederlands), uniek |
| `description` | nullable, intern |
| `show_in_clinic_portal` | bool, default `false` |
| `show_in_patient_portal` | bool, default `false` |
| `sort_order` | volgorde in lijsten |
| timestamps + `HasAuditTrail` | wijzigingen in het Wijzigingslogboek |

- `document_types.key` (nullable, unique, onveranderlijk): alleen voor de twee systeemtypen die in code
  herkend worden, `to_migrate` en `own_upload` (zie startset). Niet te bewerken en niet te verwijderen in het
  beheerscherm. Alle andere types hebben `key = null`; de API werkt met `id` en geeft `{id, label}` terug
  (afspraak "resource-links" in [00-plan](../patient-portaal/00-plan.md)).
- `activities.document_type_id`: **verplicht** voor elke file-activiteit (FK → `document_types`,
  `restrictOnDelete`). Vervangt `additional->document_type`. Bij aanmaken zonder type geeft de validatie een
  fout; geen stille default behalve in de migratie (hieronder) en bij patiënt-upload (`own_upload`).
- Verwijderen van een type wordt **geblokkeerd** zolang er documenten aan hangen (en altijd voor de
  systeemtypen). Een type verbergen gaat via de twee schakelaars.
- Migratie:
  1. tabel aanmaken en vullen met de startset (hieronder);
  2. `activities.document_type_id` vullen: bestaande `additional->document_type`-waarden mappen op de
     startset volgens de mapping hieronder; **alles zonder type of zonder mapping gaat naar
     "Te migreren"**;
  3. kolom daarna `NOT NULL` maken;
  4. de oude JSON-sleutel pas weghalen als alle lezers zijn omgezet (zie Scope).

### Startset (akkoord)

| Type | `key` | Kliniekportaal | Patiëntportaal | Opmerking |
|---|---|---|---|---|
| Te migreren | `to_migrate` | aan | aan | vangnet voor bestaande documenten zonder (herkend) type; beide aan zodat er bij de migratie niets uit het portaal verdwijnt. De beheerder zet documenten om en schakelt dit type daarna uit |
| Verslagen | | aan | aan | ook Arztbrief en OP-bericht |
| Recept | `prescription` | aan | aan | recept uit de Rezept Studio (G-6a); systeemtype omdat de code het herkent: na aanmaken vergrendeld (niet wijzigen/verwijderen) en gedeeld met het patiëntportaal |
| Beelden | | aan | aan | |
| Vragenlijsten | | aan | aan | |
| Informatie | | aan | aan | bijv. voorbereiding, folders |
| Financieel | `financial` | uit | aan | orderbevestigingen; de code die ze aanmaakt zoekt dit type op via de `key` |
| Door mij aangeleverd | `own_upload` | aan | aan | type voor patiënt-uploads ([patient-portaal/09](../patient-portaal/09-patient-upload.md)) |

De vier typen met een `key` zijn systeemtypen: niet te verwijderen, `key` niet te wijzigen. Beginwaarden van
de schakelaars zijn akkoord; daarna is alles zelf te beheren.

### Mapping bestaande documenten

Gemeten op de lokale database (`select distinct` op `activities.additional->document_type`, 4176
file-activiteiten). Draai dezelfde query op acc en prod vóór de migratie en voeg afwijkende waarden toe.

| Huidige waarde | Aantal (lokaal) | Gepubliceerd aan patiënt | Wordt |
|---|---|---|---|
| `order_confirmation` | 651 | 651 | **Financieel** |
| (geen) | 3525 | 3326 | **Te migreren** |

Andere waarden zonder mapping gaan naar "Te migreren".

## Zichtbaarheid (de twee eigenschappen)

| Eigenschap | Effect |
|---|---|
| `show_in_patient_portal` | documenten van dit type verschijnen alleen in `GET patient/{id}/documents` en in `?type=` / `preparation[]` als dit aan staat. Een type met `false` komt nooit naar de patiënt, ook niet als het document wel naar de patiënt gepubliceerd is (`activity_portal_persons`). |
| `show_in_clinic_portal` | idem voor `portal/…/documents`-endpoints van het kliniekportaal (G-6). |

Het type is een extra voorwaarde bovenop de bestaande publicatie en scope: een document is pas zichtbaar als
het zowel gepubliceerd/binnen scope is **als** het type voor dat portaal aan staat. Eén centrale query-scope
(bijv. `Activity::scopeOfTypeVisibleIn(Portal)`), zodat alle lijsten en downloads dezelfde regel gebruiken
(zie `ActivityRepository::paginateDocumentFilesForPerson()` en `publishedToPortalForPerson`).

## Scope

- Migratie en model `DocumentType` + factory, repository (patroon `ProductTypeRepository`).
- **Beheerscherm in de CRM-admin** (`Instellingen → Documenttypes`), patroon `ProductTypeController` /
  `SimpleEntityController`, `adminc.document_types.index|create|edit`, DataGrid, routes in
  `settings-routes.php`, ACL `settings.document_types.*`:
  - lijst met naam, de twee schakelaars, volgorde en aantal documenten;
  - aanmaken en bewerken (naam, omschrijving, twee schakelaars, volgorde);
  - verwijderen alleen als er geen documenten aan hangen (anders weigeren met melding; het type uitzetten voor
    beide portalen volstaat om het te verbergen);
  - label via vertaling in `lang/nl/messages.php`, zoals bij de eerdere labelwijziging.
- CRM-admin: bij uploaden/bewerken van een file-activiteit een type kiezen (keuzelijst uit de collectie).
- Query-scope voor zichtbaarheid per portaal (hierboven) en gebruiken in:
  - `ActivityRepository::paginateDocumentFilesForPerson()` (patiënt), filter `?type=` werkt op `id`(s);
  - de bestaande lezers van `additional->document_type` omzetten naar de kolom
    (`PatientDocumentResource`, `PatientDocumentController`, `PatientDocumentsIndexRequest`,
    `OrderConfirmationController`, `StoreOrderConfirmationPdf`, `RepairMissingConfirmationPdfs`).
- API: `GET patient/{id}/document-types` geeft alleen types met `show_in_patient_portal = true` (`{id, label}`),
  zodat het portaal een filter kan bouwen. Zelfde voor het kliniekportaal onder `portal/document-types`
  (komt mee met G-6).
- Documenten krijgen in de API `type: {id, label}`.

## Open vragen

Besloten: startset met beginwaarden van de schakelaars, verwijderen blokkeren, type verplicht, bestaande
documenten zonder type naar "Te migreren", `order_confirmation` naar Financieel, recept (G-6a) is een eigen
type ("Recept", `key = prescription`). Nog open:

- Mapping op acc en prod controleren (zie hierboven) vóór de migratie daar draait.

## Acceptatie

- Migratietest: een document met `additional.document_type = order_confirmation` krijgt "Financieel"; een
  document zonder of met onbekend type krijgt "Te migreren"; daarna staat geen enkel document zonder
  `document_type_id`.
- Een document van het type "Recept" is na aanmaken niet meer te wijzigen of te verwijderen (G-6a stap 5); het
  type is niet van een recept om te zetten naar een ander type of andersom zonder die regel te omzeilen
  (afstemmen met het G-6a-ticket).
- Nieuwe orderbevestigingen (`StoreOrderConfirmationPdf`, `OrderConfirmationController`,
  `RepairMissingConfirmationPdfs`) krijgen "Financieel" via de `key`, niet via de naam.
- Een bestaande orderbevestiging blijft na de migratie zichtbaar voor de patiënt en is niet zichtbaar voor het
  kliniekportaal.
- Een file-activiteit zonder type aanmaken geeft een validatiefout; een patiënt-upload krijgt `own_upload`.
- Beheerscherm: aanmaken, bewerken, volgorde en de twee schakelaars werken; verwijderen van een type met
  documenten wordt geweigerd, en de systeemtypen zijn nooit te verwijderen of van `key` te wijzigen
  (Pest, patroon `ClinicDepartmentCrudTest`); zonder permissie 403.
- Een document van een type met `show_in_patient_portal = false` staat niet in `GET patient/{id}/documents`,
  ook niet als het gepubliceerd is, en de download geeft 404.
- Hetzelfde document is wel zichtbaar voor het kliniekportaal als `show_in_clinic_portal = true`, en andersom.
- `GET patient/{id}/documents?type=` filtert op één of meer typen; typen die niet naar de patiënt mogen worden
  genegeerd (geen lek via het filter).
- Bestaande tests voor patiënt-documenten blijven groen.
