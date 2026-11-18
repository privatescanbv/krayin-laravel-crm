# 21 — Voorkeuren per kanaal en reminder-stop

**Trello:** https://trello.com/c/BiTHnTQc
**Trello-label:** `NOT_READY`

**Afhankelijk van:** 15  ·  **Grootte:** S  ·  **Prio:** S  ·  **Bron:** NOT-003, NOT-004

## Scope

- `PersonPreferenceKey` uitbreiden per kanaal (`email`, `sms`, `push`); `GET/PUT patient/{id}/preferences`
  geeft ze terug met de bestaande defaults en `is_system_managed`.
- NOT-004: de reminder-job vraagt vóór elke verzending de actiestatus op (ticket 15) en stuurt niets voor een
  afgeronde actie.

## Acceptatie

- Reminder voor een afgeronde actie wordt niet verstuurd (test met `Notification::fake()`).
