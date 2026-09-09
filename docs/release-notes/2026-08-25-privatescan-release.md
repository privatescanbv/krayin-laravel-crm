# Release Notes - Privatescan | 25 augustus 2026

---

**Onderwerpregel voor e-mail:**
`Update Privatescan - release 25 augustus 2026: betere e-mailregistratie, veiligere leadverwerking en technisch onderhoud`

---

Beste gebruiker,

Vanavond na 17:30 zal ik de volgende release gaan uitrollen. In deze release ligt de nadruk op betere registratie en controle rond e-mails in het CRM, robuustere foutafhandeling bij leadverwerking via de API en technisch onderhoud aan dependencies en documentatie.

---

## Wat is er verbeterd?

### CRM

- **Handmatig gekoppelde e-mails worden voortaan beter vastgelegd.** Bij het koppelen van een e-mail aan een dossier of ander record wordt extra auditinformatie opgeslagen, zodat beter zichtbaar is wat er is gebeurd.
- **De e-mailweergave geeft meer context bij koppelingen.** In het CRM is beter terug te zien hoe een e-mail aan een entiteit is verbonden, wat onderzoek en opvolging makkelijker maakt.
- **Leadaanmaak via de API is robuuster gemaakt.** Fouten rond het aanmaken van leads worden netter afgehandeld, waardoor koppelingen minder snel stilvallen en problemen beter traceerbaar zijn.
- **Er is een nieuw hulpmiddel toegevoegd om fout gekoppelde e-mails op te schonen.** Daarmee kunnen e-mails die per ongeluk aan een verkeerd orderrecord zijn gehangen gerichter opgespoord en hersteld worden.
- **Interne technische basis en dependencies zijn bijgewerkt.** Daarmee is de basis weer iets veiliger en onderhoudsvriendelijker geworden.

### Portaal / Forms

- **Forms loopt mee in deze release met lichte technische updates.** Er zitten deze ronde geen grote zichtbare functionele wijzigingen in het portaal, maar API-documentatie en frontend-dependencies zijn bijgewerkt zodat CRM en Forms in hetzelfde releasepad blijven.

---

## Aandachtspunten

- In deze tijdelijke release-omgeving konden geen volledige lokale geautomatiseerde tests of builds worden uitgevoerd, omdat `vendor/`- en `node_modules/`-dependencies ontbreken.
- Advies na uitrol: controleer kort een handmatig gekoppelde e-mail in het CRM, een leadaanmaak via de API en indien relevant een scenario waarin een verkeerd gekoppelde e-mail moet worden opgeschoond.

---

Met deze update wordt Privatescan vooral sterker in controleerbaarheid van e-mailstromen, foutbestendigheid van koppelingen en technisch onderhoud. Heb je vragen of merk je iets op? Laat het ons weten.

Met vriendelijke groet,
Het Privatescan development-team

---

_Releasebasis CRM: `origin/main` `91b48e911` -> `origin/development` `dd7c978da`._
_Releasebasis Forms: `origin/main` `9af2af2` -> `origin/development` `6a90feb`._
