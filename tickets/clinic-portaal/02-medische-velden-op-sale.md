# 02 — Regio, diagnose en Behandlungsberatung op de sale

**Afhankelijk van:** –  ·  **Grootte:** M  ·  **Bron:** [gap-analyse](00-gap-analyse.md) §3.1, §3.2

## Doel

De velden die de arts op kaart en patiëntkaart ziet, bestaan in de CRM en komen mee in de API.

## Scope

- Migratie op `salesleads`:
  - `region` (nullable, enum `App\Enums\SalesRegion`: `LWS`, `HWS`, met `label()`);
  - `diagnosis` (text, nullable);
  - `treatment_advice` (text, nullable), in het portaal "Behandlungsberatung".
- Model: `$fillable`, `$casts` (region → enum). Audit via `HasAuditTrail`.
- CRM-admin: velden tonen en bewerken in "sale bewerken" en "sale bekijken", alleen voor Herniapoli-sales
  (`SalesLead::isHerniaPoli()`), zoals `doctor_user_id`.
- API:
  - `ClinicSalesLeadResource` (kaart): `region: {id, label}|null`, `diagnosis`, `treatment_advice`. Platte tekst,
    geen HTML.
  - `ClinicSalesLeadDetailResource`: dezelfde velden.
  - Scribe-voorbeelden bijwerken.

## Acceptatie

- Velden opslaan en tonen in de CRM-admin; niet zichtbaar voor Privatescan-sales.
- Kaart en detail leveren de velden; lege velden zijn `null`.
- Geen extra queries per kaart (velden staan op de sale zelf).
- Wijziging verschijnt in het Wijzigingslogboek.
