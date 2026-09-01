# Release Notes - Privatescan | 1 september 2026

---

**Onderwerpregel voor e-mail:**
`Update Privatescan - release 1 september 2026: slimmere formulieren, beter anamnesis-overzicht en nettere patiëntmeldingen`

---

Beste gebruiker,

Vandaag rollen we een nieuwe Privatescan-release uit. In deze release ligt de nadruk op beter werken met diagnoseformulieren, meer overzicht in anamneses en een nettere afhandeling van meldingen en autorisaties in het CRM en het portaal.

---

## Wat is er verbeterd?

### CRM

- **Anamneses geven meer overzicht over gekoppelde formulieren.** In het CRM is een uitgebreider formulierenoverzicht toegevoegd, zodat sneller zichtbaar is welke formulieren bij een dossier horen en wat de status daarvan is.
- **Diagnoseformulieren sluiten beter aan op de bestaande formulierflow.** Het koppelen van diagnoseformulieren loopt nu meer via dezelfde bediening als de bestaande GVL-formulieren, wat het gebruik consistenter maakt.
- **Patiëntmeldingen rond formulieren zijn verbeterd.** Meldingen worden netter opgebouwd en beter gekoppeld aan formulierstatussen, zodat de opvolging richting patiënt duidelijker is.
- **Er is fijnmaziger autorisatie toegevoegd.** Voor onder meer `afletteren kliniek` is nu een aparte gebruikersrechtenlaag beschikbaar, zodat deze handelingen beter af te schermen zijn.
- **Technische foutafhandeling en logging zijn opgeschoond.** Zelfherstellende databaseproblemen, invoervalidatiefouten en vergelijkbare situaties veroorzaken minder onnodige error-logging.

### Portaal / Forms

- **Er zijn twee nieuwe diagnoseformulieren toegevoegd.** Het portaal ondersteunt nu ook formulieren voor nekpijn en onderrugpijn.
- **De formulierweergave is generieker en flexibeler gemaakt.** De nieuwe form-schemaopzet maakt het eenvoudiger om vraaggestuurde formulieren consistenter op te bouwen en weer te geven.
- **Bestandsuitvoer vanuit formulieren is verduidelijkt.** Downloads krijgen nu een betere bestandsnaam op basis van het daadwerkelijke formuliertype, in plaats van standaard op GVL te leunen.
- **Technische basis en dependency-flow zijn bijgewerkt.** Daarmee blijft Forms gelijk oplopen met CRM in het gezamenlijke releasepad.

---

## Aandachtspunten

- In deze tijdelijke release-omgeving zijn geen volledige lokale geautomatiseerde tests of builds uitgevoerd; de clone bevat geen `vendor/`- en `node_modules/`-dependencies.
- Advies na uitrol: controleer in het CRM een anamnesedossier met formulierenoverzicht en een patiëntmelding, en in Forms kort een nek- of onderrugformulier plus een download/export.

---

Met deze update wordt Privatescan vooral sterker in diagnoseformulieren, inzicht in anamneses en betrouwbaardere patiëntcommunicatie. Heb je vragen of merk je iets op? Laat het ons weten.

Met vriendelijke groet,
Het Privatescan development-team

---

_Releasebasis CRM: `origin/main` `dcda610e4` -> `origin/development` `1bdc35503`._
_Releasebasis Forms: `origin/main` `6a90feb` -> `origin/development` `b81caf5`._
