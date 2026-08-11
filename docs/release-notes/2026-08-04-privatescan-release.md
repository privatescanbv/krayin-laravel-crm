# Release Notes - Privatescan | 4 augustus 2026

---

**Onderwerpregel voor e-mail:**
`Update Privatescan - release 4 augustus 2026: slimmere activiteiten, betere orderlogica en onderhoud aan het portaal`

---

Beste gebruiker,

We hebben vandaag een nieuwe versie van Privatescan uitgerold. In deze release ligt de nadruk op gebruiksgemak in het CRM, correctere verwerking van ordergegevens en technisch onderhoud aan het portaal.

---

## Wat is er verbeterd?

### CRM

- **Activiteiten zijn flexibeler aan te passen.** Een bestaande activiteit kan nu eenvoudiger worden gewijzigd tussen een taak en een belnotitie, zonder omslachtige workaround.
- **De clinic guide koppelt activiteiten netter aan de juiste order.** Daardoor sluiten activiteiten logischer aan op de order waar ze bij horen.
- **Personen met mogelijke dubbelen vallen sneller op.** In het persoonsscherm is nu duidelijker zichtbaar wanneer er sprake kan zijn van een dubbel contact.
- **Inkoopprijs-verwerking rond orderregels is verbeterd.** De achterliggende logica voor orderitems en inkoopprijzen is aangescherpt, zodat berekeningen en verwerking consistenter verlopen.

### Portaal / Forms

- **Technisch onderhoud aan de front-end dependencies.** In het portaal zijn de JavaScript/NPM-afhankelijkheden bijgewerkt. Dit is vooral een onderhoudsrelease om de basis actueel en stabiel te houden.

---

## Aandachtspunten

- In deze omgeving konden geen lokale geautomatiseerde tests of builds worden uitgevoerd, omdat in beide lokale repo's geen `vendor/`- en `node_modules/`-dependencies aanwezig waren.
- Advies na uitrol: controleer in het CRM het wijzigen van een activiteitstype, open een order in de clinic guide en verifieer een orderregel waarbij inkoopprijslogica meespeelt.

---

Met deze update wordt Privatescan gebruiksvriendelijker in het dagelijkse CRM-werk en blijft ook de technische basis van het portaal netjes bijgewerkt. Heb je vragen of merk je iets op? Laat het ons weten.

Met vriendelijke groet,
Het Privatescan development-team

---

_Releasebasis CRM: `origin/main` `c10125c78` -> `origin/development` `668be7133`._
_Releasebasis Forms: `origin/main` `9f1a7f0` -> `origin/development` `2586c7c`._
