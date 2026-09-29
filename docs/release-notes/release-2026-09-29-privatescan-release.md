# Release Notes - Privatescan | 29 september 2026

---

**Onderwerpregel voor e-mail:**
`Update Privatescan - release 29 september 2026: beter patiëntoverzicht, rapportagerechten en formulierfeedback`

---

Beste gebruiker,

Vandaag brengen we een gezamenlijke Privatescan-release uit voor CRM en Forms. Deze update verbetert het overzicht in CRM, maakt rapportages beter beheersbaar en geeft patiënten duidelijkere terugkoppeling in het portaal.

---

## Wat is er verbeterd?

### CRM

- **Overzicht op betalingen verbeterd.** Orders met een open aanbetaling blijven zichtbaar in het betalingsoverzicht.
- **Patiëntgegevens betrouwbaarder in datumvelden.** De datumkiezer toont de juiste persoon bij het werken met patiëntgegevens.
- **Gerichter rapportagetoegang.** De rechten voor CRM-rapportages zijn gecorrigeerd.
- **Sneller zoeken in personenoverzicht.** Er zijn naamfilters toegevoegd aan de personenlijst.
- **Rollen duidelijker ingericht.** De rechten rond orderregels zijn overzichtelijker geordend.
- **Technisch onderhoud uitgevoerd.** Wekelijkse afhankelijkheidsupdates en een code-reviewronde zijn meegenomen.

### Portaal / Forms

- **Duidelijkere hulp bij wachtwoordherstel.** Patiënten krijgen betere feedback wanneer zij een wachtwoordherstel aanvragen voor een onbekend e-mailadres.
- **Technisch onderhoud uitgevoerd.** De wekelijkse afhankelijkheidsupdates zijn meegenomen.

---

## Aandachtspunten

- In deze tijdelijke release-omgeving zijn geen volledige lokale geautomatiseerde tests of builds uitgevoerd; de clones bevatten geen `vendor/`- en `node_modules/`-dependencies.
- Advies na uitrol: controleer het betalingsoverzicht met een open aanbetaling, de rapportagerechten, zoeken op naam en een wachtwoordherstel voor een onbekend e-mailadres.

---

Met vriendelijke groet,
Mark & Mark

---

_Releasebasis CRM/Forms: `a8282d9be` / `1f0468cc6` -> `81c10b058` / `f7aa98344`._
