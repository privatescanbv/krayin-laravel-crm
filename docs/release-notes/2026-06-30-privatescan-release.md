# Release Notes - Privatescan | 30 juni 2026

---

**Onderwerpregel voor e-mail:**
`Update Privatescan - release 30 juni 2026: betere e-mailafzender en verbeterde portaalweergave`

---

Beste gebruiker,

We hebben vandaag een nieuwe versie van Privatescan uitgerold. Deze release bevat een verbetering in het CRM voor het versturen van e-mails vanuit de juiste mailbox en een visuele verbetering in het portaal voor de weergave van afspraak- en dossierinformatie.

---

## Wat is er verbeterd?

### CRM - e-mail vanuit de juiste mailbox

- **E-mails kiezen automatisch de juiste afzender.** Bij e-mails vanuit orders, leads en sales leads bepaalt het CRM nu beter of de e-mail vanuit PrivateScan of HerniaPoli moet worden verstuurd.
- **Minder kans op verzendfouten via Microsoft Graph.** Als er geen afzender is ingevuld, vult het CRM de afzender automatisch met het e-mailadres van de gekozen mailbox. Daarmee wordt voorkomen dat Microsoft Graph de e-mail weigert omdat de afzender niet overeenkomt met het gebruikte account.
- **Betere ondersteuning voor sales leads.** Sales leads kunnen nu ook worden gebruikt om de juiste mailbox te bepalen, inclusief terugval naar de gekoppelde lead wanneer dat nodig is.

### Portaal / Forms - layoutverbetering

- **Langere teksten passen beter in de kaartweergave.** In de portaalweergave zijn layoutregels aangepast zodat lange namen of teksten beter afbreken en minder snel buiten het blok lopen.
- **Stabielere weergave van afspraakdetails.** De indeling van de kopregel en rechterkolom is flexibeler gemaakt, waardoor de weergave rustiger blijft bij langere inhoud.

---

## Aandachtspunten

- Er zijn geen GitHub Actions-checks of statuschecks gevonden op de releasecommits. De wijzigingen zijn daarom beoordeeld op basis van de commit-diff.
- Voor het CRM is testdekking toegevoegd rond het bepalen van de juiste mailbox voor leads, orders en sales leads.
- Advies na uitrol: test een e-mail vanuit een PrivateScan-order en een HerniaPoli-order, en controleer in het portaal een afspraak/dossier met langere tekst of naam.

---

Met deze update wordt het verzenden van e-mail betrouwbaarder en blijft de portaalweergave netter bij langere inhoud. Heb je vragen of loop je ergens tegenaan? Laat het ons weten.

Met vriendelijke groet,
Het Privatescan development-team

---

_Releasebasis CRM: `main` / `development` op commit `7696cdb46dc5144e01399aa5bc31647395c6f56f`._
_Releasebasis Forms: `main` / `development` op commit `35dfc59e7ac3b697da86a4565bc125fed1045446`._
