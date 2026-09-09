# Release Notes - Privatescan | 21 augustus 2026

---

**Onderwerpregel voor e-mail:**
`Update Privatescan - release 21 augustus 2026: veiliger samenvoegen, betere dubbelenherkenning en slimmer technisch onderhoud`

---

Beste gebruiker,

Vanavond na 17:30 zal ik de volgende release gaan uitrollen. In deze release ligt de nadruk op veiliger samenvoegen van personen en leads, beter herkennen en terugmelden van dubbelen, en technisch onderhoud rond caches, portalveiligheid en documentatie.

---

## Wat is er verbeterd?

### CRM

- **Samenvoegen van personen is veiliger gemaakt.** Extra beveiliging voorkomt nu dat personen met portaaltoegang onbedoeld op een verkeerde manier worden samengevoegd.
- **Dubbelen van personen en leads worden beter herkend.** De matching is verbreed en consistenter gemaakt, zodat vermoedelijke dubbelen sneller en in beide richtingen zichtbaar worden.
- **Foutieve dubbelenmarkeringen zijn terug te draaien.** Medewerkers kunnen een false positive expliciet herstellen, waardoor de dubbelenlijst betrouwbaarder blijft.
- **Tellers en signalering rond dubbelen zijn verbeterd.** Het CRM laat consistenter zien hoeveel relevante dubbelen er zijn, ook nadat records wijzigen of een status wisselt.
- **Caches en mail-/accountafhandeling zijn slimmer gemaakt.** Rond Keycloak-accountgegevens en duplicate caches is onderhoud toegevoegd, zodat gegevens sneller kloppen en minder snel verouderen.
- **API-documentatie en interne technische basis zijn bijgewerkt.** De Scribe-bestanden en ondersteunende technische onderdelen zijn mee bijgewerkt voor een beter onderhoudbare basis.

### Portaal / Forms

- **Geen grote zichtbare functionele wijziging in Forms deze ronde.** Forms loopt wel mee in deze release, zodat CRM en Forms weer gelijk in releasebeheer blijven.

---

## Aandachtspunten

- In deze tijdelijke release-omgeving konden geen volledige lokale geautomatiseerde tests of builds worden uitgevoerd, omdat `vendor/`- en `node_modules/`-dependencies ontbreken.
- Advies na uitrol: controleer in het CRM kort een persoonskaart met dubbelsignalering, een merge-scenario van personen of leads, een false-positive/undo-scenario en een gebruiker met portaaltoegang.

---

Met deze update wordt Privatescan vooral sterker in datakwaliteit, veiliger beheer van persoonsgegevens en technische onderhoudbaarheid. Heb je vragen of merk je iets op? Laat het ons weten.

Met vriendelijke groet,
Het Privatescan development-team

---

_Releasebasis CRM: `origin/main` `99b003963` -> `origin/development` `414f690b8`._
_Releasebasis Forms: `origin/main` `9af2af2` -> `origin/development` `9af2af2`._
