# Release Notes - Privatescan | 20 augustus 2026

---

**Onderwerpregel voor e-mail:**
`Update Privatescan - release 20 augustus 2026: betere dubbelsignalering, slimmere suggesties en technisch onderhoud`

---

Beste gebruiker,

We hebben vandaag een nieuwe versie van Privatescan klaargezet. In deze release ligt de nadruk op het sneller herkennen van dubbele personen, beter ondersteunen van medewerkers tijdens het bewerken en koppelen van leads, en technisch onderhoud rond stabiliteit en security.

---

## Wat is er verbeterd?

### CRM

- **Dubbele personen vallen sneller op.** In het CRM is zichtbaarer gemaakt wanneer een persoon vermoedelijk dubbelen heeft, zodat opschonen eerder en gerichter kan gebeuren.
- **Suggesties tijdens het bewerken van leads zijn slimmer geworden.** Bij het koppelen of beoordelen van personen en leads wordt nu breder gematcht, ook als voornamen niet exact gelijk zijn. Dat helpt om bestaande dossiers sneller terug te vinden.
- **Samenvoegen van leads is veiliger gemaakt.** Rond het mergen van leads met gekoppelde verkoopinformatie is extra logica toegevoegd, zodat de juiste hoofdlead beter behouden blijft.
- **Mailkoppelingen geven meer context.** Bij het koppelen van een lead of verkooprecord aan e-mail zie je duidelijker status en aanmaakdatum, en actieve records komen logischer naar voren.
- **Login- en platformtechniek zijn bijgewerkt.** De Keycloak-gerelateerde afhandeling en onderliggende dependencies zijn verder bijgewerkt voor een stabielere en beter onderhoudbare basis.

### Portaal / Forms

- **Geen grote zichtbare functionele wijziging in Forms deze ronde.** Forms loopt wel mee in deze release en heeft technisch onderhoud en dependency-updates gekregen.

---

## Aandachtspunten

- In deze tijdelijke release-omgeving konden geen volledige lokale geautomatiseerde tests of builds worden uitgevoerd, omdat `vendor/`- en `node_modules/`-dependencies ontbreken.
- Advies na uitrol: controleer in het CRM kort een persoonskaart met dubbelsignalering, het bewerken of koppelen van een lead, een merge-scenario met verkoopcontext en een login via de gebruikelijke route.

---

Met deze update wordt Privatescan vooral sterker in datakwaliteit, gebruiksgemak bij leadverwerking en technische onderhoudbaarheid. Heb je vragen of merk je iets op? Laat het ons weten.

Met vriendelijke groet,
Het Privatescan development-team

---

_Releasebasis CRM: `origin/main` `3122aaa67` -> `origin/development` `640087325`._
_Releasebasis Forms: `origin/main` `8b9f9cc59` -> `origin/development` `9af2af2`._
