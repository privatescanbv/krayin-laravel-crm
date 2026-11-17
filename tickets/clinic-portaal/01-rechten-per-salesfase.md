# 01 — Rechten per salesfase per rol

**Afhankelijk van:** –  ·  **Grootte:** M  ·  **Bron:** [gap-analyse](00-gap-analyse.md) §3.3

## Doel

De beheerder bepaalt in de CRM per Hernia-salesfase welke rollen (Arts, Kliniek medewerker) die fase zien. Het
kliniekportaal krijgt alleen die fases als werkstatus (kolommen), en sales in andere fases zijn voor die gebruiker
onzichtbaar.

## Stand

- Koppeltabel `lead_pipeline_stage_roles` bestaat (2026-11-05) en is leeg.
- `Stage::scopeVisibleForRole()`: geen rijen = zichtbaar voor **alle** rollen.
- `GET clinic/{id}/sales-stages`, `scopeForPortalDoctor()` en `updateStage` gebruiken `visibleForRole()` al. Een
  stagewissel naar een niet-toegestane fase wordt dus al geweigerd.
- Beheer kan alleen via de database.

## Scope

- **Betekenis expliciet maken:** een fase is alleen zichtbaar voor een rol als er een rij is.
  `Stage::scopeVisibleForRole()` aanpassen (`whereHas('roles', …)` zonder de `whereDoesntHave`-tak).
  Gevolg: zonder rijen ziet geen portaalrol de fase.
- **Migratie:** voor elke Hernia-salesfase (pipeline `PIPELINE_HERNIA_SALES_ID`, niet verwijderd) een rij voor
  rol Arts en rol Kliniek medewerker (via `RoleCode`, niet via vaste id's). Idempotent (`insertOrIgnore`).
- **Nieuwe fases** die later in deze pipeline worden aangemaakt krijgen niet automatisch rechten; de beheerder
  zet ze aan. Bij het aanmaken een duidelijke standaard: geen rechten (expliciet). Vermelden in het beheerscherm.
- **Beheer in de CRM-admin:** in het bewerkscherm van de pipeline (`settings/pipelines/edit`), per fase
  aanvinken: "Zichtbaar voor Arts", "Zichtbaar voor Kliniek medewerker". Alleen tonen bij de Hernia-salespipeline
  (of bij elke pipeline als dat eenvoudiger is; de portaal-API leest alleen de Hernia-salespipeline).
  Opslaan via `PipelineController::update`. Wijzigingen in het Wijzigingslogboek.
- Rollen als lijst uit `RoleCode` (ARTS, KLINIEK_MEDEWERKER), niet hard in de view.

## Acceptatie

- Na de migratie ziet de arts dezelfde kolommen als nu (alle Hernia-salesfases).
- Vinkje "Arts" uit bij een fase → `GET sales-stages` levert die fase niet meer, sales in die fase verdwijnen
  uit `GET sales-leads`, detail en notities geven 404, en `PUT …/stage` naar die fase geeft een fout.
- Beide vinkjes uit → de fase is voor geen enkele portaalrol zichtbaar (niet meer "voor iedereen").
- Beheerscherm: vinkjes opslaan en tonen; zonder permissie op pipelines geen toegang.
- Bestaande tests in `tests/Feature/Api/clinic` aangepast aan de expliciete betekenis en groen.
