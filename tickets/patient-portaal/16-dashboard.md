# 16 — `counters` uitbreiden tot dashboard

**Trello:** https://trello.com/c/NlqLeIr1
**Trello-label:** `NOT_READY`

**Afhankelijk van:** 02, 08, 11, 15  ·  **Grootte:** M  ·  **Prio:** M  ·  **Bron:** DASH-001…005, K9

## Stand van zaken

`GET patient/{id}/counters` bestaat al (`PatientCounterController`, route `api.patient.counters`). Het geeft
alleen tellers voor de menubadges:

| Veld nu | Betekenis |
|---|---|
| `new_messages_count` | ongelezen berichten van het team |
| `new_appointments_count` | aantal toekomstige afspraken |
| `new_docs_count` | **open formulieren** (de naam klopt niet, K9) |

## Doel

Hetzelfde endpoint levert alles voor het dashboard. Geen nieuw endpoint: `counters` wordt uitgebreid en krijgt
correcte veldnamen.

## Scope

- Nieuwe velden naast de bestaande (de oude blijven tot het oude portaal weg is):
  - `unread_messages` (= `new_messages_count`), `open_forms` (= wat nu `new_docs_count` heet);
  - `new_documents`: ongeziene documenten ([08](08-documents-seen-and-filters.md));
  - `next_appointment: {id, label, start_at}`, de eerstvolgende afspraak zelf in plaats van alleen het aantal;
  - `open_actions`: `{count, items[3]}` ([15](15-actions.md));
  - `sales[]`: `{id, label, status: {id, label}}` van actieve sales ([02](02-sales-endpoint.md));
  - `contact` uit `config/patient_portal.php`.
- `new_docs_count` krijgt `deprecated` in de Scribe-docs; `new_messages_count` en `new_appointments_count`
  blijven voorlopig bestaan.
- De Forms-aanroep mag het endpoint niet breken (huidig gedrag blijft: bij een fout `open_forms` = -1 en een
  waarschuwing in de log).

## Acceptatie

- Elk getal is getest tegen de bron.
- Bestaande `counters`-tests blijven groen (oude velden ongewijzigd).
- Aantal queries blijft constant (geen telling per sale in een lus).
