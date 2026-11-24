# 04 — Zoeken op naam en filter "Abgeschlossen"

- **Status:** gebouwd, nog niet gecommit en niet handmatig getest (2026-11-24)
  - Repo/branch: krayin-laravel-crm, `development`, ongecommit in de werkmap.
  - Gedaan: `q` (elk woord in voor- of achternaam van één gekoppelde persoon, deelwoorden, hoofdletterongevoelig) en `closed` op `GET clinic/{id}/sales-leads` via `SalesLead::scopeWherePersonNameMatches` / `scopeClosedStage`; `meta.counts.open|closed` (2 count-queries, respecteren `q`, niet `closed`/`stage_id`); Form Request + Scribe bijgewerkt; tests in `ClinicSalesLeadsTest`.
  - Afwijking: **standaard toont de lijst nu alleen open sales** (zoals het ticket vraagt); afgeronde sales verschijnen niet meer zonder `closed=1`. Het portaal moet dat weten.
  - Nog: het portaal gebruiken (`clinic-portal`), "Wartet" blijft buiten scope.

**Afhankelijk van:** 01  ·  **Grootte:** S  ·  **Bron:** [gap-analyse](00-gap-analyse.md) §3.1

Het filter "Wartet" is niet in dit ticket: de betekenis is nog open.

## Scope

- `GET clinic/{id}/sales-leads?q=`: zoeken op voor- en/of achternaam van de gekoppelde personen, binnen
  `forPortalDoctor()`. Hoofdletterongevoelig, deelwoorden.
- `?closed=1`: alleen afgeronde sales (fase `is_won` of `is_lost`); zonder parameter alleen open sales.
  Afgeronde fases tellen alleen mee als de rol er rechten op heeft (ticket 01).
- Tellingen in `meta` van dezelfde response: `counts.open`, `counts.closed` (voor het label "Abgeschlossen 1421"),
  zonder extra aanroep. Tellingen respecteren `q`.
- Scribe en Form Request (`ClinicSalesLeadIndexRequest`) bijwerken.

## Acceptatie

- Zoeken vindt alleen eigen sales; een naam van een patiënt van een andere arts geeft niets.
- `closed=1` toont afgeronde sales; standaard niet.
- `meta.counts` klopt met de aantallen per filter.
- Aantal queries blijft constant.
