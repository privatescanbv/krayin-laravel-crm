# Release Notes - Privatescan | 8 september 2026

---

**Onderwerpregel voor e-mail:**
`Update Privatescan - release 8 september 2026: stabielere e-mailverwerking en technische basis op peil`

---

Beste gebruiker,

Vandaag brengen we een nieuwe Privatescan-release uit. Deze release richt zich vooral op het stabieler maken van e-mailverwerking en het op peil houden van de technische basis. Er zijn geen grote wijzigingen in de bediening; het werk zit in betrouwbaarheid en onderhoud.

---

## Wat is er verbeterd?

### CRM

- **E-mailberichten zijn nu betrouwbaarder te verwijderen.** Een melding rond het verwijderen van e-mail is aangepast zodat dit beter en zonder problemen verloopt.
- **Minder fouten rond het versturen van e-mail.** We hebben een tijdsbeperking (throttle) toegevoegd op de mailverbinding, waardoor veel minder fouten optreden wanneer er veel berichten tegelijk worden verwerkt.
- **De technische basis is bijgewerkt.** De wekelijkse update van libraries en pakketten (npm/composer) is doorgevoerd, zodat het systeem veilig en onderhoudbaar blijft.

### Portaal / Forms

- **Robuustere afhandeling van inlogsessies.** De verwerking van verouderde of verlopen sessies (HTTP 401) is verbeterd, zodat het portaal netter en foutlozer reageert wanneer een sessie niet meer geldig is.
- **De technische basis is bijgewerkt.** Ook Forms loopt mee met de wekelijkse update van libraries en pakketten, zodat beide systemen in het gezamenlijke releasepad gelijk blijven oplopen.

---

## Aandachtspunten

- In deze tijdelijke release-omgeving zijn geen volledige lokale geautomatiseerde tests of builds uitgevoerd; de clone bevat geen `vendor/`- en `node_modules/`-dependencies.
- Advies na uitrol: stuur in het CRM een testmail en verwijder een e-mailbericht, en log in Forms kort in/uit om de sessieafhandeling te controleren.

---

Met deze update wordt Privatescan stabieler in de dagelijkse e-mailverwerking en blijft de technische basis gezond. Heb je vragen of merk je iets op? Laat het ons weten.

Met vriendelijke groet,
Het Privatescan development-team

---

_Releasebasis CRM: `origin/main` `60cd478d6` -> `origin/development` `96eeb1cb8`._
_Releasebasis Forms: `origin/main` `b81caf53f` -> `origin/development` `994100222`._
