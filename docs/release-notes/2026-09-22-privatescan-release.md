# Release Notes - Privatescan | 22 september 2026

---

**Onderwerpregel voor e-mail:**
`Update Privatescan - release 22 september 2026: dashboards, patiëntportaal en slimmere contactacties`

---

Beste gebruiker,

Vandaag brengen we een gezamenlijke Privatescan-release uit voor CRM en Forms. Deze update maakt managementinformatie toegankelijker, geeft meer inzicht in het patiëntportaal en verbetert veelgebruikte contactacties.

---

## Wat is er verbeterd?

### CRM

- **Metabase-dashboards direct in CRM.** Dashboards kunnen nu ingebed in het CRM worden bekeken, waardoor inzichten sneller beschikbaar zijn tijdens het werk.
- **Inzicht in actieve patiëntportaalsessies.** Het dashboard toont nu het aantal actieve sessies in het patiëntportaal.
- **Contactacties werken sneller.** Bij een nieuwe e-mailactie wordt het e-mailadres van de contactpersoon automatisch ingevuld.
- **Telefoonnummers zijn aanklikbaar.** Nummers in activiteiten en taken kunnen direct worden aangeklikt om te bellen.
- **Technische stabiliteit verbeterd.** Lokale Keycloak-ontwikkeling en de analytische database zijn gecorrigeerd; afhankelijkheden zijn bijgewerkt.

### Portaal / Forms

- **Validatie geboortedatum verbeterd.** Een ingevulde geboortedatum wordt gevalideerd op een minimumleeftijd van 12 jaar.
- **Formulieren verder geharmoniseerd.** GVL-formulieren zijn technisch verder geabstraheerd voor consistentere verwerking en onderhoud.
- **Afhankelijkheden bijgewerkt.** Het wekelijkse onderhoud aan npm- en Composer-pakketten is meegenomen.

---

## Aandachtspunten

- In deze tijdelijke release-omgeving zijn geen volledige lokale geautomatiseerde tests of builds uitgevoerd; de clones bevatten geen `vendor/`- en `node_modules/`-dependencies.
- Advies na uitrol: controleer een ingebed dashboard, de sessieteller van het patiëntportaal, een e-mailactie en de validatie van een geboortedatum.

---

Met vriendelijke groet,
Het Privatescan development-team

---

_Releasebasis CRM/Forms: `200e9f051` / `b6e7695ac` -> `be689a0d9` / `199cc1e13`._
