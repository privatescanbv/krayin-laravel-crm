# Clinic portaal — werkzaamheden

Korte takenlijst om later uit te werken tot user stories. Twee delen: wat het CRM moet bewaren, en wat het clinic portaal moet tonen.

**Bronnen (peildatum 22-09-2026):** Monday-app v45 Draft (`Herniapoli-monday-broncode-2026-09-22`), patient/clinic portaal in `privateforms`, CRM in deze repo.

Het portaal presenteert. Het CRM bewaart. Monday is nu allebei; die twee gaan uit elkaar.

Zorgadviseur-bord, arts-cockpit en weekplanner horen bij deze levering. Ze staan nu in Monday en moeten in het portaal beschikbaar komen, anders kan Monday voor die rollen niet uit.

---

## Deel 1 — CRM

Het CRM hoeft geen clinic-schermen te krijgen. Het moet de operatiedag gaan bewaren en die via een nieuwe clinic-API aan het portaal geven. Planning, personen en orders blijven zoals ze zijn.

Het slot (`resource_orderitem`) blijft planning. De uitvoering komt op een nieuw operatierecord, zodat verzetten of annuleren het klinische verslag niet wist.

Clinic Guide (`/admin/clinic-guide`) is het query-patroon voor “wie staat er die dag”, maar niet de API. Die response bevat `total_price` en is read-only admin.

### Blijft staan

- `Person`, `SalesLead`, `Order`, `OrderItem`, `ResourceOrderItem`, shifts en de hernia-pipelines.
- Planningdata in het CRM (shifts, slots, resources). De weekplanner-UI komt in het portaal en praat via de clinic-API.
- Werkstatus van Monday (wie is aan zet) blijft een eigen veld. Die overlapt in naam met de sales-pipeline, niet in betekenis.
- Anamnesis en het narcoseformulier in privateforms. Het CRM levert de link, het portaal toont het formulier.

### Taken

Elke taak is één latere user story. Kern is de operatiedag. Taak 7 en 8 horen bij het zorgadviseur-bord, de arts-cockpit en de weekplanner.

**1. Operatierecord**
Nieuw model, één per geplande of uitgevoerde ingreep, gekoppeld aan `order_item_id` en optioneel aan het slot. Velden: anesthesiestatus + toelichting, werkelijke OP-tijd, kamer, voorovernachting, bijzonderheden, hechtingen, ontslagdatum/-tijd, medicatie tijdens opname, medicatie mee, recepttekst, flags “aangemeld bij kliniek” en “documenten ontvangen”. Anesthesie heeft een enum; een onbekende waarde wordt nooit stilzwijgend “akkoord”. Kamer is tekst, geen nieuwe Resource.

**2. Kliniek op de gebruiker**
Koppeling Keycloak-gebruiker → één `clinic_id`, met rol `clinic`, `guide`, `advisor` of `doctor`. CRM-users hebben die koppeling nu niet; `clinic_coordinator_user_id` op de order is een andere rol. Elke clinic-query filtert hierop. De kliniek van een order loopt via resource → afdeling → clinic, niet via een kolom op `orders`.

**3. Documenttypes**
Vaste types op bestaande file-opslag (`activity_files` / `additional.document_type`), gekoppeld aan traject of operatierecord: diagnoseformulier, anesthesieformulier, Arztbrief I, MRT, CT, OP-bericht, Procelsio-OP-bericht, medicatielijst. De aanmeldcheck (verplicht: diagnoseformulier, anesthesieformulier, Arztbrief I; AFB geldt niet voor Herniapoli) moet hierop kunnen. De Arztbrief zelf wordt niet in het CRM opgebouwd; Document Studio in Monday levert de PDF (taak 9). Recept genereren blijft buiten deze levering.

**4. Clinic-API**
Nieuw blok naast `patient/{id}`, zelfde auth-patroon (API-key of Keycloak), altijd gescoped op de kliniek van de gebruiker. Geen prijzen, geen OP-techniek, geen inkoop.

| Pad | Doel |
|---|---|
| `GET /clinic/me` | Kliniek, rol, naam |
| `GET /clinic/home?date=` | Home per rol (kliniek, begeleider, zorgadviseur, arts) |
| `GET /clinic/day?date=` | Patiënten en operaties die dag |
| `GET /clinic/patients/{person}` | Dossier, alleen als de persoon op deze kliniek staat |
| `GET/PATCH /clinic/operations/{record}` | Lezen en schrijven van kliniekvelden |
| `GET/POST …/documents` + download | Typed bestanden |

Home Kliniek levert drie blokken: operaties vandaag, nieuwe aanmeldingen met documentcheck, ontslag (schrijft het record; zonder record geen formulier). Home Begeleider levert de eerstvolgende kliniekdag en drie anesthesie-bakken: te controleren, formulier nog niet ontvangen, gecontroleerd. Ontbrekend formulier is niet hetzelfde als “nog niet gecontroleerd”. De begeleider schrijft niet vanaf Home.

**5. Autorisatie en audit**
Policy per endpoint. `clinic` schrijft kliniekvelden. `guide` leest de dag en zet anesthesie via het dossier. `advisor` en `doctor` zien het bord en de cockpit, en schrijven alleen hun eigen velden (werkstatus, beoordeling, verslagstatus). Elke write via `HasAuditTrail`. Feature tests voor scoping (andere kliniek geeft 404), verborgen financiële velden, en de anesthesie-mapping.

**6. Migratie Monday → CRM**
Apart van het bouwen, wel nodig vóór cutover. Patiënten naar `Person`, afspraken naar slots, operatierecords en bestanden naar de nieuwe tabellen, werkstatus mee. Read-only tegen de Monday-borden, schrijven alleen naar het CRM. Historie overschrijft nooit een bestaand record.

**7. Werkstatus, los van de pipeline**
Eigen veld op het traject (`SalesLead`), niet de hernia-sales-stages. Waarden zoals in Monday: casus bij arts ter beoordeling, vraag aan arts, Arztbrief nodig, OP-verslag nodig, beoordeling gereed, vraag beantwoord, Arztbrief verzendklaar, OP-verslag verzendklaar, wachten, afgerond. Het bord en de arts-cockpit filteren hierop. De sales-pipeline blijft de patiëntreis.

Vraag en antwoord tussen zorgadviseur en arts is een notitie op het traject (bestaande `Activity`), met een merkteken “eerst de notitie lezen”. Geen aparte chat.

**8. Planning-API voor de weekplanner**
Lezen en schrijven van bestaande slots, gescoped op de kliniek. Afspraaktype (Operatie, Consult, Nacontrole), planningsstatus (Vrij, Optie, Bevestigd, Geannuleerd), intake-datum, aankomsttijd, optie-verval, reden annulering, “tijd bevestigd aan patiënt”. Vrije capaciteit is een slot zonder patiënt; een afspraak met patiënt wordt nooit verwijderd. Het CRM-planningsmodel blijft de bron. De UI staat in het portaal.

**9. Arztbrief vanuit Monday**
Document Studio blijft in de Monday-app. De generator (`arztbrief-core.js`, sjabloon, handtekening, A4-preview) wordt niet nagebouwd. De studio wijzigt Monday-data nu niet, behalve de uiteindelijke PDF op de kolom Arztbrief I.

Integratie is dat ene schrijfpunt verleggen naar het CRM:

- Invoer die de brief als waarheid gebruikt (naam, geboortedatum) komt uit `Person`. Wijkt het diagnoseformulier af, dan blokkeert opslaan tot iemand dat bevestigt — die controle bestaat al, nu tegen Monday.
- Het diagnoseformulier wordt als bestand uit het CRM gelezen (documenttype uit taak 3), niet uit de Monday-kolom.
- Opslaan plaatst de PDF als documenttype Arztbrief I op het traject. Oude versies blijven staan. Het portaal en de aanmeldcheck zien dat bestand.
- Het Monday-traject moet aan het CRM-traject hangen (`SalesLead`), anders weet de upload niet waar het bestand hoort.

Sjabloon en handtekening blijven in de browser van de arts (zoals nu, `localStorage`). De zorgadviseur verstuurt de brief vanuit het portaal zodra de werkstatus op Arztbrief verzendklaar staat.

### Niet in deze CRM-levering

Rezept-generator, Monday-veldmatrix, en een Procelsio-koppeling. Aanmelden blijft een vlag op het operatierecord. De Arztbrief-generator blijft in Monday.

---

## Deel 2 — Clinic portaal

Zelfde patroon als het patient portaal, in `privateforms`. Nu is het een stub: login, rol `clinic` of `employee`, en een welkomstpagina op `/clinic/` en `clinic.local.privatescan.nl`. Geen daglijst, geen dossier, geen schrijfacties.

```
Medewerker (kliniek, begeleider, zorgadviseur, arts)
  → Clinic portaal (Blade, Keycloak)
    → CRM clinic-API
      → CRM-modellen
```

Eigen navigatie. De stub hergebruikt nu de patiënt-layout. Duits als default voor kliniek en arts, Nederlands voor zorgadviseur en begeleider. De i18n-laag van privateforms bestaat; de teksten nog niet.

Het portaal toont geen `total_price`, geen OP-techniek en geen inkoop.

### Blijft staan

- Keycloak-login en 2FA, zoals het patient portaal.
- Formulieren invullen blijft patiëntwerk. De kliniek en de begeleider mogen het narcoseformulier inzien; die routes hangen nu niet onder `portal.clinic`.
- CRM-admin Clinic Guide blijft het interne scherm. Het portaal bouwt zijn eigen daglijst op de clinic-API.

### Taken

Elke taak is één latere user story. Data komt uit deel 1.

**1. Schil**
Navigatie, rol na login, koppeling aan één kliniek, lege staten, fout als de gebruiker geen kliniek heeft. Home kiest het rolscherm. Taal: DE voor `clinic` en `doctor`, NL voor `advisor` en `guide`.

**2. Home Kliniek**
Drie blokken, in deze volgorde: operaties vandaag (bevestigd, met type en tijd), nieuwe aanmeldingen (documentcheck + vlaggen aangemeld / documenten ontvangen), ontslag vastleggen. Ontslagdatum is verplicht; tijd en medicatie mee gaan mee. Zonder operatierecord geen formulier, wel een uitleg. Zelfde schrijfpad als het operatiedetail.

**3. Home Begeleider**
Eerstvolgende kliniekdag (Operatie-intake, Consult of Nacontrole; geen vaste weekdag). Drie anesthesie-bakken: te controleren, formulier nog niet ontvangen, gecontroleerd. Ontbrekend formulier is een eigen bak. PDF-dagplanning (A4) vanaf deze home. De begeleider schrijft niet vanaf Home; anesthesie gaat via het dossier.

**4. Dossier en operatiedetail**
Dossierkop: patiënt, regio, diagnose, advies, narcose-status, afspraken, huidig operatierecord. Operatiedetail in twee delen. Voorbereiding: anesthesie en verplichte documenten. Operatiedag en ontslag: kamer, OP-tijd, hechtingen, medicatie, ontslag, OP-berichten. Klik vanuit elke home opent dit dossier.

**5. Documenten en narcose**
Typed lijst, upload en download, gekoppeld aan traject of operatierecord. Narcoseformulier openen onder de clinic-rol (alleen inzien).

**6. Zorgadviseur-bord**
Twee lagen, zoals in Monday.

Home, van boven naar beneden: hoeveel acties er openstaan, daarna de blokken beoordelingen gereed, antwoord van de arts, brieven klaar om te verzenden, voorbereiding operaties, uitgevoerde operaties, consulten en nacontroles. Belstrip “morgen bevestigen” alleen op een dag waarop er iemand gebeld moet worden.

Daaronder het werkstatus-bord: één bak per werkstatus uit taak 7 van het CRM. Filter op de hele werkvoorraad van deze kliniek. Een rij opent het dossier. Werkstatus wijzigen schrijft het veld op het traject, niet de sales-pipeline. “Siehe Chat” betekent: er staat een notitie die je eerst leest.

**7. Arts-cockpit**
Home met vier blokken: te beoordelen, vraag aan arts, Arztbrief nodig, OP-verslag nodig. Een rij opent het dossier. De arts zet de werkstatus door (beoordeling gereed, vraag beantwoord, Arztbrief verzendklaar, OP-verslag verzendklaar). Een vraag van de zorgadviseur en het antwoord zijn de notitie op het traject. Plannen doet de arts niet. Arztbrief I maken opent Document Studio in Monday; de PDF komt terug in het dossier als document. Recept genereren zit niet in dit scherm.

**8. Weekplanner**
Weekrooster van de kliniek: Operatie, Consult en Nacontrole, plus vrije capaciteit. Status Vrij, Optie, Bevestigd, Geannuleerd, met optie-verval en reden van annulering. Een operatieweek aanmaken is vrije slots. Een slot met patiënt claimen, bevestigen of annuleren gaat via de planning-API. Een gevulde afspraak verdwijnt niet als je capaciteit opruimt. Dit vervangt het Monday-weekrooster, niet het CRM-datamodel.

### Niet in het portaal voor deze levering

Rezept Studio, de Monday-veldmatrix, en een live Procelsio-koppeling. Document Studio (Arztbrief I) blijft in Monday en levert de PDF aan het CRM. Interne chat blijft een notitie op het traject.
