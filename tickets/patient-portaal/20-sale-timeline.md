# 20 — Tijdlijn per sale

**Afhankelijk van:** 01, [portaal/02](../portaal/02-appointment-detail.md)  ·  **Grootte:** M  ·  **Prio:** S  ·  **Bron:** TRJ-005

## Scope

- `GET patient/{id}/sales/{saleId}/timeline`, afgeleid (geen nieuwe tabel):
  - wissels van de patiëntstatus (ticket 01), uit de "Status gewijzigd"-activiteiten in het Wijzigingslogboek
    (zelfde bron als `SalesLead::scopeWithStageEnteredAt`); alleen als de patiëntstatus verandert;
  - afspraken ([portaal/02](../portaal/02-appointment-detail.md));
  - gepubliceerde uitslagen ([portaal/01](../portaal/01-document-type.md), type "verslag", mits zichtbaar voor de patiënt).
- Per item: `at`, `label`, `kind`, `target: {id, label}`. Alleen mijlpalen, nooit interne fases.

## Acceptatie

- Twee interne fasewissels binnen dezelfde patiëntstatus geven één tijdlijnitem.
