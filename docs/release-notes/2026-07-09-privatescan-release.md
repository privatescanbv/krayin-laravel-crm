# Release Notes - Privatescan | 9 juli 2026

---

**Onderwerpregel voor e-mail:**
`Update Privatescan - release 9 juli 2026: slimmere checks, betere e-mailverwerking en veiliger portaal`

---

Beste gebruiker,

We hebben vandaag een nieuwe versie van Privatescan uitgerold. In deze release zijn verbeteringen doorgevoerd in zowel het CRM als het portaal. De nadruk ligt op betrouwbaardere order- en rapportverwerking, nettere e-mailafhandeling en extra beveiliging in het portaal.

---

## Wat is er verbeterd?

### CRM

- **Checks en rapportages werken beter per persoon.** Bij orders met meerdere personen worden checks nu unieker per persoon verwerkt. Ook is de rapportage-upload flexibeler gemaakt, zodat ontbrekende `check_ids` minder snel problemen geven.
- **Betere e-mailafhandeling in het CRM.** E-mails blijven beter aan de juiste conversatie gekoppeld, reply-ketens zijn hersteld en berichten worden consistenter naar de juiste map verplaatst.
- **Overzichtelijkere order- en rapportverwerking.** Het systeem ondersteunt nieuwe rapportages beter, toont data consistenter en verwerkt importen van orders, activiteiten en notities stabieler.
- **AFB- en medische gegevens worden netter verwerkt.** Een aantal omzettingen en validaties rond AFB-velden en medicatiegegevens zijn aangescherpt, waardoor informatie betrouwbaarder wordt opgeslagen.
- **Kleine gebruiksverbeteringen in het dagelijks werk.** Denk aan duidelijkere onderwerpen in het mailoverzicht, een logischer standaarddeadline bij activiteiten en het verwijderen van een overbodig clinic-veld op plekken waar dat verwarring gaf.

### Portaal / Forms

- **Portaalbeveiliging is verder aangescherpt.** Rond toegangscontrole en documentstromen zijn extra veiligheidsverbeteringen doorgevoerd.
- **Formulierteksten zijn op details opgeschoond.** Onder andere de vraag over jodium is duidelijker gemaakt door overbodige beschrijvende tekst weg te halen.
- **Documentafhandeling is robuuster gemaakt.** Bestandsrechten en documenttoegang zijn consistenter gemaakt, zodat documenten betrouwbaarder beschikbaar blijven.

---

## Aandachtspunten

- In deze omgeving konden geen geautomatiseerde tests of build-checks worden uitgevoerd, omdat in beide lokale repo's geen `vendor/`-dependencies aanwezig zijn.
- Advies na uitrol: test in het CRM een order met meerdere personen en een rapportage-upload, controleer daarna een e-mailreply vanuit het activiteiten-/mailoverzicht en verifieer in het portaal een documentdownload en formulierdoorloop.

---

Met deze update wordt Privatescan betrouwbaarder in de dagelijkse verwerking van orders, e-mails en portaaltoegang. Heb je vragen of loop je ergens tegenaan? Laat het ons weten.

Met vriendelijke groet,
Het Privatescan development-team

---

_Releasebasis CRM: `origin/main` `0ec9ae51b` -> `origin/development` `e621597da`._
_Releasebasis Forms: `origin/main` `e07c7be` -> `origin/development` `c9dd368`._
