# CRM prototype

Open `index.html` rechtstreeks in de browser. Geen installatie of build nodig.

- Orders en Sales: kanban, lijst, zoeken en filters voor pipeline, medewerker en Win/Lost.
- Klik op een kaart of op Bekijken voor een volledige detailpagina, met contactpaneel, sectienavigatie, fasebalk en inklapbare AI-kolom zoals in het CRM. Aanmaken en bewerken hebben ook eigen pagina's.
- Orders: checks, ordergegevens, AFB-status, gekoppelde Sales & Lead, persoon, activiteiten, afletteren, betalingen en anamnese.
- Sales: leadgegevens, persoon, activiteiten, anamnese, gekoppelde orders en marketing.
- Fases wijzigen, notities/activiteiten toevoegen, checks toevoegen/afvinken en CRUD werken met fictieve voorbeelddata.
- De hash-URL bewaart het geopende record en de sectie. Herladen, browser-terug en doorlinken tussen Sales en Orders werken.
- Orders kunnen aan een salesrecord worden gekoppeld. Bij verwijderen van sales wordt die koppeling leeggemaakt; de order blijft behouden.
- Wijzigingen worden in localStorage bewaard. Gebruik **Voorbeelddata herstellen** om opnieuw te beginnen.
- `prototype.css` bevat de vormgeving; `prototype.js` de voorbeelddata en interactie.

Gebaseerd op de lokale Orders- en Sales-schermen op 3 november 2026, inclusief de detailpagina's en Blade-templates. De pipeline Herniapoli gebruikt voorlopig dezelfde fases als Privatescan. De editor bevat de basisvelden en de Orders-tabs Details/Checks; gespecialiseerde bewerkvelden zijn nog niet volledig nagebouwd. Betaling, organisaties en contacten zijn velden in het prototype; er is geen CRM-, betaal-, plannings-, bestands-, AFB- of AI-koppeling. Niet-gekoppelde actieknoppen melden dit bij aanklikken. Anamnese en afletteren tonen een lege toestand; betalingen toont een voorbeeld op basis van de betaalstatus. Gebruik alleen fictieve gegevens.

Voor browsercontroles kun je vanuit deze map `python3 -m http.server 8765` starten en `http://localhost:8765` openen.

`tests/browser-check.js` bevat een herhaalbare Playwright-controlefunctie die een `Page` accepteert. Deze controleert pagina-/bewerknavigatie, CRUD, herladen, browser-terug, fases, tekstescaping, gekoppelde orders en breedtes van 390, 768, 1280 en 1600 pixels tegen `http://127.0.0.1:8765`. De controle maakt eigen records aan en verwijdert die weer.
