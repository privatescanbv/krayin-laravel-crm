# Privatescan release — 6 oktober 2026

**Onderwerp:** Privatescan-update — dashboards, dossierkwaliteit en stabiliteit

Beste collega,

Vandaag is een nieuwe update van Privatescan beschikbaar gekomen. Deze release verbetert onder andere de dashboards, de kwaliteit van persoons- en activiteitsgegevens en de afhandeling van dubbele dossiers.

## CRM

- Nieuwe en verbeterde dashboards voor Herniapoli, orders en Metabase, met duidelijkere Nederlandse labels, vaste filters en betere weergave van titels.
- Extra beoordeling- en onderzoeksvelden voor het Herniapoli-verkoopproces, inclusief beheerbare opties voor de beoordelingsuitkomst.
- Samenvoegen van personen kan voortaan ongedaan worden gemaakt.
- Betere verwerking van fout-positieve dubbele personen: de cache wordt gecorrigeerd zodat een onjuist duplicaat-icoon verdwijnt.
- Activiteitenlog toont voortaan begrijpelijke namen voor pipelinefase, bron en eigenaar in plaats van alleen interne IDs.
- Verbeteringen in de performance van het orderdashboard en de dagelijkse AFB-verzending.
- Reguliere npm- en Composer-dependencyupdates.

## Forms

- Reguliere npm- en Composer-dependencyupdates.

## Controle en uitrol

- In deze tijdelijke release-omgeving zijn geen volledige lokale geautomatiseerde tests of builds uitgevoerd; de clones bevatten geen `vendor/`- en `node_modules/`-dependencies.
- Advies na uitrol: controleer de Herniapoli-dashboards, het terugdraaien van een persoonsmerge, een dossier zonder echte duplicaten en de orderdashboardperformance.

---

Met vriendelijke groet,
Mark & Mark

---

_Releasebasis CRM/Forms: `14e8650a8` / `f7aa983` -> `d7f1c19a9` / `e5a7392`._
