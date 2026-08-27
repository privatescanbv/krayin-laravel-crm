# Release Notes - Privatescan | 27 augustus 2026

---

**Onderwerpregel voor e-mail:**
`Update Privatescan - release 27 augustus 2026: betere duplicaatcontrole, stabielere matchsuggesties en e-mailverbeteringen`

---

Beste gebruiker,

Vanavond na 17:30 zal ik de volgende release gaan uitrollen. In deze release ligt de nadruk op betere controle rond mogelijke dubbele personen in het CRM, consistenter gedrag bij matchsuggesties tijdens leadverwerking en een verbetering in de verwerking van e-mails met inline afbeeldingen.

---

## Wat is er verbeterd?

### CRM

- **Dubbele personen worden nauwkeuriger behandeld.** In het CRM is het nu beter mogelijk om een portaalaccount-dubbel expliciet als geen duplicaat te markeren, waardoor foutieve duplicate-signalen minder lang blijven hangen.
- **De duplicatenstatus wordt netter opgeschoond.** Bij het openen van een duplicatenpagina wordt een verouderde `has_duplicates`-status voortaan beter hersteld, zodat het overzicht beter overeenkomt met de werkelijke situatie.
- **Matchsuggesties bij personen in leads zijn consistenter gemaakt.** De suggestielogica tussen aanmaken en bewerken van leads is gelijkgetrokken, waardoor dezelfde persoon minder snel verschillend wordt voorgesteld in vergelijkbare situaties.
- **De caching achter duplicaatcontrole is verbeterd.** Daardoor worden controles rond mogelijke dubbelen stabieler en voorspelbaarder.
- **E-mails met inline afbeeldingen worden beter weergegeven.** `cid:`-verwijzingen in e-mailinhoud worden nu correcter opgelost, zodat ingesloten afbeeldingen beter renderen in het CRM.
- **Er is een hulpmiddel toegevoegd om afwijkingen in e-mailrelaties op te sporen.** Daarmee kunnen mismatches in gekoppelde e-mails gerichter worden onderzocht en hersteld.
- **Kleine technische en UX-verbeteringen zijn meegenomen.** Onder meer rond UI-tests en een feedbackwidget voor de MB Orchestrator.

### Portaal / Forms

- **Geen grote zichtbare functionele wijziging in Forms deze ronde.** Forms loopt wel mee in deze release, zodat CRM en Forms in hetzelfde releasepad blijven.

---

## Aandachtspunten

- In deze tijdelijke release-omgeving konden geen volledige lokale geautomatiseerde tests of builds worden uitgevoerd, omdat `vendor/`- en `node_modules/`-dependencies ontbreken.
- Advies na uitrol: controleer kort een duplicate-scenario bij personen, een leadflow met matchsuggesties en een e-mail met inline afbeeldingen.

---

Met deze update wordt Privatescan vooral sterker in duplicate-afhandeling, voorspelbaarheid van persoonssuggesties en betrouwbaardere e-mailweergave. Heb je vragen of merk je iets op? Laat het ons weten.

Met vriendelijke groet,
Het Privatescan development-team

---

_Releasebasis CRM: `origin/main` `7db49f2f3` -> `origin/development` `0255c1b51`._
_Releasebasis Forms: `origin/main` `6a90feb` -> `origin/development` `6a90feb`._
