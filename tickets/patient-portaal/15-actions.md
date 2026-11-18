# 15 — Patiëntacties (`actions`)

**Trello:** https://trello.com/c/Kb8zGR49
**Trello-label:** `NOT_READY`

**Afhankelijk van:** 09, 14  ·  **Grootte:** M  ·  **Prio:** M  ·  **Bron:** api-voorstel §4.2, DASH-002

## Doel

"Door jou te doen": één lijst met alles wat de patiënt nog moet doen, per sale.

## Scope

- `GET patient/{id}/actions?sale_id=`: voegt bestaande bronnen samen, geen nieuwe tabel.

  | Type | Bron | Klaar wanneer |
  |---|---|---|
  | `form` | ticket 14 | formulier definitief verzonden |
  | `document_request` | `Activity type=task` met `additional.requested_document_type`, gepubliceerd naar de patiënt | upload met dat `request_id` (ticket 09) |
  | `payment` | ticket 17 | betaald |
  | `appointment_confirm` | `OrderPersonConfirmation` | bevestigd |

- Per actie: `type`, `label`, `sale: {id, label}`, `due_at`, `target: {id, label}` (waar de actie wordt gedaan).
- CRM-admin: een document opvragen bij de patiënt (taak aanmaken + publiceren).

## Acceptatie

- Een afgeronde actie verdwijnt uit de lijst.
- Acties van een andere persoon zijn nooit zichtbaar.
