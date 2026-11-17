# 13 — Persoonsgegevens: whitelist, huisarts/noodcontact, correctieverzoek

**Afhankelijk van:** –  ·  **Grootte:** M  ·  **Prio:** M/S  ·  **Bron:** K14, DATA-001…004

## Scope

- `GET patient/{id}/details` (opvolger van `naw`): huidige velden + `editable_fields[]` + `updated_at`.
- DATA-003: `general_practitioner{name, practice, phone}` en `emergency_contact{name, relationship, phone}`.
  De velden staan al op `Person` (2026-11-03), alleen nog niet in de patiënt-API.
- `PATCH patient/{id}/details`: alleen contactgegevens, adres, huisarts en noodcontact (whitelist).
  Kerngegevens (naam, geboortedatum, geslacht) weigeren. Audit via `HasAuditTrail`.
- `POST patient/{id}/details/correction-requests`: correctieverzoek voor kerngegevens → taak in de werkvoorraad
  (`Activity type=task` op de persoon).
- Oude `naw`-routes laten werken tot het oude portaal weg is, maar met dezelfde whitelist.

## Acceptatie

- PATCH met `first_name` geeft 422; contactgegevens wijzigen lukt en staat in het Wijzigingslogboek.
- Correctieverzoek maakt precies één taak.
