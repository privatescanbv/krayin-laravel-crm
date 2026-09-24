# Release Notes - Privatescan | 24 september 2026

---

**Onderwerpregel voor e-mail:**
`Update Privatescan - release 24 september 2026: HerniaPoli-relaties, dashboards en betrouwbaarder patiëntportaal`

---

Beste gebruiker,

Vandaag brengen we een gezamenlijke Privatescan-release uit voor CRM en Forms. De update maakt de HerniaPoli-werkwijze beter ondersteund, verbetert de betrouwbaarheid van dashboards en versterkt de terugkoppeling naar patiënten.

---

## Wat is er verbeterd?

### CRM

- **HerniaPoli-relaties direct vastleggen.** Vanuit Privatescan kan nu een salesrelatie voor HerniaPoli worden aangemaakt, met een duidelijke één-op-éénrelatie en zonder automatisch een order te creëren.
- **Pipelinebeheer verbeterd.** Pipeline-fases ondersteunen soft delete, zodat historische gegevens zorgvuldig behouden blijven.
- **Werkbakken beter bereikbaar.** De benodigde rechten voor de werkbakkenpagina zijn toegevoegd.
- **Dashboards betrouwbaarder.** De koppeling naar CRM vanuit Metabase en de synchronisatie van de analytische database zijn gecorrigeerd.
- **Gebruikersrechten aangescherpt.** Problemen rond rechten zijn opgelost.

### Portaal / Forms

- **Duidelijke melding bij technische problemen.** Patiënten krijgen een herkenbare melding wanneer er een technisch probleem optreedt.
- **Afspraken blijven zichtbaar na impersonation.** Ontbrekende afspraken na herstarten van impersonation zijn hersteld.

---

## Aandachtspunten

- In deze tijdelijke release-omgeving zijn geen volledige lokale geautomatiseerde tests of builds uitgevoerd; de clones bevatten geen `vendor/`- en `node_modules/`-dependencies.
- Advies na uitrol: maak een HerniaPoli-relatie aan, open een Metabase-dashboard, controleer de werkbakkenrechten en doorloop het patiëntportaal na een impersonation-sessie.

---

Met vriendelijke groet,
Het Privatescan development-team

---

_Releasebasis CRM/Forms: `74a191b92` / `0e7223ac4` -> `b82135af6` / `1f0468cc6`._
