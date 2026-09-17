# Release Notes - Privatescan | 17 september 2026

---

**Onderwerpregel voor e-mail:**
`Update Privatescan - release 17 september 2026: sneller werken met leads en bestanden, plus technische stabiliteit`

---

Beste gebruiker,

Vandaag brengen we een nieuwe gezamenlijke Privatescan-release uit voor CRM en het portaal. Deze update maakt het werken met leads, orders en bestanden prettiger en verbetert tegelijk de technische stabiliteit.

---

## Wat is er verbeterd?

### CRM

- **Bestanden zijn eenvoudiger te uploaden.** Uploadvelden ondersteunen nu drag-and-drop, zodat documenten sneller toegevoegd kunnen worden.
- **Sneller overzicht in borden.** De kanbanweergave voor leads, sales en orders is geoptimaliseerd voor betere prestaties.
- **Betere controle bij gewonnen leads.** Bij het afronden van een lead wordt nu gevalideerd dat de geboortedatum van de persoon is ingevuld.
- **Leads en orders beter doorzetten.** Een GVL kan worden overgenomen naar een nieuwe order.
- **Marketinginformatie bij samengevoegde leads is betrouwbaarder.** Er is ondersteuning toegevoegd om te kiezen welke marketinggegevens behouden blijven en deze gegevens worden per lead eenduidig vastgelegd.
- **Stabiliteit en onderhoud.** Fouten rond speciale tekens in leadbeschrijvingen en bestandscomponenten zijn opgelost; afhankelijkheden en testondersteuning zijn bijgewerkt.

### Portaal / Forms

- **Technische basis bijgewerkt.** De yarn-dependencies zijn bijgewerkt.
- **Beveiligingsonderhoud uitgevoerd.** Browser-compatibiliteitspakketten zijn opgehoogd om Dependabot-meldingen op te lossen.

---

## Aandachtspunten

- In deze tijdelijke release-omgeving zijn geen volledige lokale geautomatiseerde tests of builds uitgevoerd; de clones bevatten geen `vendor/`- en `node_modules/`-dependencies.
- Advies na uitrol: test het uploaden via drag-and-drop, het afronden van een lead en de kanbanweergave van leads/orders.

---

Met deze update werkt Privatescan prettiger bij de dagelijkse opvolging van leads en documenten, terwijl CRM en Forms technisch actueel blijven.

Met vriendelijke groet,
Het Privatescan development-team

---

_Releasebasis CRM/Forms: `9c5ad543c` / `1d2d3d328` -> `7a93ee1d5` / `b6e7695ac`._
