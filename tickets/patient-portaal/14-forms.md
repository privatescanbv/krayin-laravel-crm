# 14 — Formulieren per sale

**Trello:** https://trello.com/c/BV59ul2C
**Trello-label:** `NOT_READY`

**Afhankelijk van:** 02  ·  **Grootte:** M  ·  **Prio:** M  ·  **Bron:** FORM-001, 004, 005, 006

De formulieren zelf leven in Forms. Dit ticket is alleen de CRM-kant.

## Scope

- `GET patient/{id}/forms?status=open|submitted&sale_id=`: toewijzing, status, deadline, geschatte duur,
  `sale: {id, label}`, open-url naar Forms. Bron: Anamnesis/GVL + `FormService`.
- `FormService::getOpenForms()` telt nu alleen per persoon. Nagaan of Forms een filter per sale kan geven;
  anders de koppeling sale ↔ formulier in het CRM bepalen (Anamnesis heeft `sales_id`).
- FORM-004: `PUT api/forms/{id}/status` (API-key, Forms → CRM) zet `submitted` + versie; daarna in het CRM
  alleen-lezen. Eerst nagaan of `leads/{id}/forms` dit al dekt.
- FORM-006: `POST api/forms/{id}/alerts` (API-key, Forms → CRM) maakt een taak met hoge prioriteit op de sale,
  idempotent op `alert_key`.

## Acceptatie

- Lijst per sale toont alleen formulieren van die sale.
- Alert met dezelfde `alert_key` maakt geen tweede taak.
