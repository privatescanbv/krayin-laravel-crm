# 11 — Berichten: categorie, sale, bijlagen, cursor

**Trello:** https://trello.com/c/QN48GAKm
**Trello-label:** `NOT_READY`

**Afhankelijk van:** –  ·  **Grootte:** M  ·  **Prio:** M  ·  **Bron:** K10, K11, MSG-001…006, V-P6

## Scope

- Kolommen `patient_messages.category` en `patient_messages.sales_lead_id` (nullable).
- `config/patient_portal.php`: berichtcategorieën met `expected_response` en de groep/werkvoorraad waar
  een bericht heen gaat.
- `GET patient/{id}/message-categories` uit die config.
- `POST patient/{id}/messages`: `category` verplicht, `sale_id` optioneel (moet van deze persoon zijn),
  bijlagen als file-activiteit (zelfde regels als upload, ticket 09). Routering naar de groep uit de config.
- `GET patient/{id}/messages?sale_id=&before=`: aflopend gesorteerd met cursor-paginering. Elk bericht krijgt
  `sale: {id, label}`.
- `POST patient/{id}/messages/read` met `up_to_id` in plaats van "alles".
- MSG-005: meldingsmail controleren, alleen "je hebt een nieuw bericht" + link, geen inhoud.

## Acceptatie

- Pagina 1 bevat het nieuwste bericht.
- `read` met `up_to_id` laat nieuwere berichten ongelezen.
- Een bericht zonder categorie geeft 422.
