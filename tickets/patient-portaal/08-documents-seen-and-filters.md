# 08 — Documenten: gezien-status en filters

**Afhankelijk van:** portaal/01  ·  **Grootte:** S  ·  **Prio:** M  ·  **Bron:** DASH-003, DOC-001, DOC-004, DOC-005

## Scope

- Kolom `activity_portal_persons.seen_at`.
- `POST patient/{id}/documents/{documentId}/seen` zet `seen_at` (idempotent).
- `GET patient/{id}/documents`: veld `seen` (bool), filter `?type=` (een of meer `document_type`-waarden, uit [portaal/01](../portaal/01-document-type.md)) en `?sale_id=`
  (ticket 03). Elk document krijgt `sale: {id, label}` en `type: {id, label}`.
- DOC-004: test die expliciet bevestigt dat ongepubliceerde (concept/intern) documenten niet in de lijst staan
  en 404 geven bij download.

## Acceptatie

- `seen` wordt `true` na de POST; een download zet het niet vanzelf (geen zijwerking op GET).
- Filter op `type` (ook meerdere waarden) en sale.
