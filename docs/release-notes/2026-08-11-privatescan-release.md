# Release Notes - Privatescan | 11 augustus 2026

---

**Onderwerpregel voor e-mail:**
`Update Privatescan - release 11 augustus 2026: betere dubbelen-merge, sterkere orderverwerking en veiliger CRM`

---

Beste gebruiker,

We hebben vandaag een nieuwe versie van Privatescan klaargezet. In deze release zitten zowel zichtbare verbeteringen in het CRM als technisch onderhoud op security en stabiliteit. De nadruk ligt op beter samenvoegen van dubbelen, betrouwbaardere order- en inkoopverwerking, nettere e-mailafhandeling en een veiliger technische basis.

---

## Wat is er verbeterd?

### CRM

- **Dubbele personen en leads zijn beter samen te voegen.** Overzichten, merge-logica en herstelroutines zijn aangescherpt, waardoor dubbelen netter verwerkt worden en minder snel vervuilde relaties of losse gegevens achterblijven.
- **Order-, inkoop- en prijsverwerking is betrouwbaarder gemaakt.** Rond orderregels, inkoopfacturen, order-item-prijzen en correcties op gewonnen/verloren statussen zijn meerdere verbeteringen doorgevoerd, zodat verwerking consistenter verloopt.
- **Rapportages zijn verder uitgebreid.** De rapportages per maand en per medewerker zijn vernieuwd en geven meer grip op omzet- en prestatie-inzichten.
- **E-mailafhandeling in het CRM is verbeterd.** E-mails kunnen beter losgekoppeld worden, schermen zijn opgeschoond en de verwerking van Microsoft Graph-mailtokens is stabieler gemaakt.
- **Activiteiten en clinic guide werken logischer.** Activiteiten kunnen beter tussen taak en belnotitie worden omgezet en koppelen consistenter aan de juiste order of context.
- **Security- en stabiliteitsverbeteringen in de technische basis.** Dependencies zijn bijgewerkt, waaronder beveiligingsupdates voor `phpseclib` en `league/commonmark`, en foutafhandeling geeft nu betrouwbaarder de juiste statuscodes terug.

### Portaal / Forms

- **Geen aparte nieuwe productiefunctionaliteit in deze release.** De huidige productieversie van Forms was al leidend; we hebben de branchstand gecontroleerd en weer gelijkgetrokken met productie.

---

## Aandachtspunten

- In deze omgeving konden geen volledige lokale geautomatiseerde tests of builds worden uitgevoerd, omdat in de tijdelijke clones geen `vendor/`- en `node_modules/`-dependencies aanwezig zijn.
- Advies na uitrol: controleer in het CRM kort een dubbelen-merge, een order-/inkoopflow, een e-mailactie vanuit het mailoverzicht en een rapportageweergave. Voor Forms volstaat een korte controle van een standaardformulier of portaalroute.

---

Met deze update wordt Privatescan vooral sterker in datakwaliteit, orderverwerking, e-mailafhandeling en technische betrouwbaarheid. Heb je vragen of merk je iets op? Laat het ons weten.

Met vriendelijke groet,
Het Privatescan development-team

---

_Releasebasis CRM: `origin/main` `6eb46338f` -> `origin/development` `bbbc90e0b`._
_Releasebasis Forms: `origin/main` `e6d85e4` -> `origin/development` `460a715` (development liep achter op production; geen nieuwe Forms-productiecode in deze release)._
