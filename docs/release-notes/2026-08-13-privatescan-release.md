# Release Notes - Privatescan | 13 augustus 2026

---

**Onderwerpregel voor e-mail:**
`Update Privatescan - release 13 augustus 2026: slimmer dubbelen zoeken, handmatig samenvoegen en stabieler inloggen`

---

Beste gebruiker,

We hebben vandaag een nieuwe versie van Privatescan klaargezet. In deze release ligt de nadruk op beter werken met dubbele leads, meer controle bij het handmatig samenvoegen van gegevens, stabielere mail- en gebruikersafhandeling en technisch onderhoud rond authenticatie.

---

## Wat is er verbeterd?

### CRM

- **Dubbele leads zijn gerichter terug te vinden.** De zoek- en detectielogica voor dubbelen is uitgebreid, waardoor vergelijkbare leads sneller zichtbaar worden en beter beoordeeld kunnen worden.
- **Leads kunnen nu ook handmatig vanuit de detailpagina worden samengevoegd.** Daardoor is het eenvoudiger om losse of dubbel ingevoerde records direct op de juiste plek op te schonen.
- **E-mailafhandeling is netter gemaakt.** In het mailoverzicht is de zichtbaarheid van e-mailhandtekeningen verbeterd, zodat berichten consistenter weergegeven worden.
- **Gebruikersbeheer is robuuster geworden.** Bij het wijzigen van een afdeling blijft het wachtwoord nu correct behouden, wat onnodige verstoringen voor gebruikers voorkomt.
- **Inloggen en sessiebeheer zijn technisch versterkt.** De Keycloak-koppeling is bijgewerkt naar een nieuwere basis en portaalgebruikers zonder voornaam worden netter afgehandeld bij het inloggen.

### Portaal / Forms

- **Geen aparte functionele wijziging in Forms deze ronde.** Forms loopt wel mee in de release-set, maar bevat ten opzichte van de vorige release geen nieuwe zichtbare aanpassing.

---

## Aandachtspunten

- In deze omgeving konden geen volledige lokale geautomatiseerde tests of builds worden uitgevoerd, omdat in de tijdelijke clones geen `vendor/`- en `node_modules/`-dependencies aanwezig zijn.
- Advies na uitrol: controleer in het CRM kort een duplicaatzoekactie, een handmatige merge vanaf een lead-detailpagina, een mailweergave met handtekening en een loginflow via het portaal of Keycloak.

---

Met deze update wordt Privatescan vooral sterker in datakwaliteit, gebruiksgemak bij merges en betrouwbaarheid van login- en mailafhandeling. Heb je vragen of merk je iets op? Laat het ons weten.

Met vriendelijke groet,
Het Privatescan development-team

---

_Releasebasis CRM: `origin/main` `bab89cc51` -> `origin/development` `fe112c11a`._
_Releasebasis Forms: `origin/main` `8b9f9cc59` -> `origin/development` `8b9f9cc59`._
