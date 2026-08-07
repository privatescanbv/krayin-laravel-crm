# Release Notes - Privatescan | 6 augustus 2026

---

**Onderwerpregel voor e-mail:**
`Update Privatescan - release 6 augustus 2026: samenvoegen dubbelen, veiliger dependencies en stabielere imports`

---

Beste gebruiker,

We hebben vandaag een nieuwe versie van Privatescan uitgerold. In deze release ligt de nadruk op het netter samenvoegen van dubbele personen en leads, stabielere importverwerking en technisch onderhoud aan beveiliging en dependencies.

---

## Wat is er verbeterd?

### CRM

- **Dubbele personen zijn beter samen te voegen.** Het proces voor het samenvoegen van dubbele personen is verbeterd, inclusief nettere verwerking van relaties, veldmappings en controles op achterblijvende gegevens.
- **Ook dubbele leads worden betrouwbaarder afgehandeld.** Het samenvoegen van leads is aangescherpt, zodat dit consistenter verloopt en minder kans geeft op vervuilde of incomplete data.
- **Ordertaken uit imports worden stabieler verwerkt.** Bij het importeren van orders worden taken nu in chunks verwerkt, wat de kans op vastlopers of piekbelasting verlaagt.
- **Technische onderhouds- en securityverbeteringen.** Dependencies zijn opgeschoond en bijgewerkt, waaronder een beveiligingsupdate voor `phpseclib`.

### Portaal / Forms

- **Technisch onderhoud aan dependencies en documentatie.** In Forms zijn dependencies en gerelateerde lockfiles bijgewerkt. Dit is vooral onderhoud om de basis actueel en betrouwbaarder te houden.

---

## Aandachtspunten

- In deze omgeving konden geen lokale geautomatiseerde tests of builds worden uitgevoerd, omdat in beide lokale repo's geen `vendor/`- en niet overal complete front-end dependencies beschikbaar waren.
- Advies na uitrol: test in het CRM het samenvoegen van een dubbel persoon en een dubbele lead, en controleer daarna een orderimport. Verifieer in Forms kort een standaardformulier en documentweergave.

---

Met deze update wordt Privatescan betrouwbaarder in datakwaliteit, importverwerking en technisch onderhoud. Heb je vragen of merk je iets op? Laat het ons weten.

Met vriendelijke groet,
Het Privatescan development-team

---

_Releasebasis CRM: `origin/main` `e7e6c673c` -> `origin/development` `47451d585`._
_Releasebasis Forms: `origin/main` `2586c7c3a` -> `origin/development` `befbafb5d`._
