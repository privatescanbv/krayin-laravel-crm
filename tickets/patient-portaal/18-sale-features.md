# 18 — Features per sale-type

**Trello:** https://trello.com/c/Ixuklf60
**Trello-label:** `NOT_READY`

**Afhankelijk van:** 02  ·  **Grootte:** S  ·  **Prio:** M  ·  **Bron:** TRJ-003

## Doel

Het portaal toont per sale alleen de blokken die erbij horen (bijv. Herniapoli: verzekering en nazorg;
Privatescan: uitslagen).

## Scope

- `config/patient_portal.php`: `features` per sale-type (afdeling + producttype), bijv. `forms`, `payments`,
  `results`, `insurance`, `aftercare`.
- `type: {id, label}` en `features[]` in het sale-detail (ticket 02).

## Acceptatie

- Test per afdeling dat de juiste features meekomen.
