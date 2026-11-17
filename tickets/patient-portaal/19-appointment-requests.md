# 19 — Afspraakverzoek wijzigen/annuleren

**Afhankelijk van:** [portaal/02](../portaal/02-appointment-detail.md)  ·  **Grootte:** S  ·  **Prio:** S  ·  **Bron:** APPT-004

## Scope

- `POST patient/{id}/appointments/{appointmentId}/requests`: `type=change|cancel`, `reason`, `preference`.
- Maakt een taak-activiteit op de order (werkvoorraad).
- Idempotent per afspraak zolang er een open verzoek is: een tweede verzoek geeft het bestaande terug.

## Acceptatie

- Verzoek op een afspraak van een ander geeft 404.
- Tweede verzoek maakt geen tweede taak.
