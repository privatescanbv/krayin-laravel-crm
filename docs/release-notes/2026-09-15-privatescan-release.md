# Release Notes - Privatescan | 15 september 2026

---

**Onderwerpregel voor e-mail:**
`Update Privatescan - release 15 september 2026: slimmere leadinzichten, factuuraudit en sterkere rapportagebasis`

---

Beste gebruiker,

Vandaag brengen we een nieuwe gezamenlijke Privatescan-release uit voor CRM en het portaal. De nadruk ligt op beter inzicht in leads, betere vastlegging rond facturen en een robuustere basis voor rapportages en analytics.

---

## Wat is er verbeterd?

### CRM

- **Sneller inzicht in leads.** Leads en andere entiteiten kunnen nu van AI-gegenereerde samenvattingen en feedback worden voorzien, zodat belangrijke informatie sneller te beoordelen is.
- **Betere controle bij het afletteren van facturen.** Rond facturen is extra auditregistratie toegevoegd en kan de onderzoeksdatum vanuit het scherm worden aangepast.
- **Betrouwbaardere relatiegegevens.** Leads met een gekoppelde salesrelatie kunnen niet meer per ongeluk worden verwijderd.
- **Sterkere rapportage- en analyticsbasis.** De synchronisatie met Metabase is verbeterd en de benodigde tabellen, campagnegegevens en herstelondersteuning zijn uitgebreid.
- **Technisch onderhoud en security.** De wekelijkse npm/composer-updates zijn uitgevoerd; ook is een high-severity browserslist-beveiligingsupdate meegenomen.

### Portaal / Forms

- **Technische basis bijgewerkt.** De wekelijkse npm/composer-updates zijn doorgevoerd zodat Forms gelijk oploopt met het CRM.
- **Documentatie voor API-gebruik vernieuwd.** De Scribe-documentatie is bijgewerkt.

---

## Aandachtspunten

- In deze tijdelijke release-omgeving zijn geen volledige lokale geautomatiseerde tests of builds uitgevoerd; de clones bevatten geen `vendor/`- en `node_modules/`-dependencies.
- Advies na uitrol: controleer een AI-leadsamenvatting, een factuur-aflettering inclusief auditinformatie en een Metabase/analytics-overzicht.

---

Met deze update krijgt Privatescan meer houvast in leadopvolging, factuurcontrole en rapportage, terwijl CRM en Forms technisch actueel blijven.

Met vriendelijke groet,
Het Privatescan development-team

---

_Releasebasis CRM/Forms: `307949d2f` / `994100222` -> `fbc38d48e` / `1d2d3d328`._
