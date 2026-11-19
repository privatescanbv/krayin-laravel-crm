# 09 — Patiënt-upload: soort, sale en verwerkingsstatus

**Trello:** https://trello.com/c/IcTxdf8D
**Trello-label:** `NOT_READY`

**Afhankelijk van:** portaal/01  ·  **Grootte:** M  ·  **Prio:** M  ·  **Bron:** K13, DOC-002, DOC-003, V-P7

## Scope

- `POST patient/{id}/documents`: extra velden `sale_id` (moet van deze persoon zijn) en optioneel
  `request_id` (gevraagd document, ticket 15).
- De lege closure-validatie in `PatientFileUploadController` weg; validatie in een Form Request.
- `additional.processing_status` op de file-activiteit: `received` → `in_review` → `accepted` | `rejected`,
  met een patiëntvriendelijke `rejection_reason`.
- CRM-admin: status zetten op een patiënt-upload (alleen nodig als V-P7 "goedkeuren" wordt).
- Een patiënt-upload krijgt altijd het systeemtype "Extern aangeleverd" (`key = own_upload`,
  [portaal/01](../portaal/01-document-type.md)); `document_type_id` uit de request is dus niet nodig. De
  medewerker kan het type later omzetten in de CRM-admin.
- `GET patient/{id}/documents?source=own`: eigen uploads met `processing_status`, ook zonder publicatie.

## Acceptatie

- Upload met een `sale_id` van een andere persoon geeft 422.
- Eigen upload staat met status `received` in `?source=own`, en heeft het type "Extern aangeleverd".
