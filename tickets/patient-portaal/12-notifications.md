# 12 — Meldingen: lezen zonder zijwerking, read/dismiss

**Trello:** https://trello.com/c/vqKx0ibn
**Trello-label:** `NOT_READY`

**Afhankelijk van:** –  ·  **Grootte:** S  ·  **Prio:** M  ·  **Bron:** K7, K8, NOT-001, NOT-002

## Scope

- `GET patient/{id}/notifications` markeert niets meer als gelezen.
- `POST patient/{id}/notifications/read` (`ids[]` of `all`): zet `read_at`, mag altijd.
- `POST patient/{id}/notifications/{notificationId}/dismiss`: zet `dismissed_at`, alleen voor dismissable.
- Kolom `read_at` als die er niet is; "gelezen" en "weggeklikt" los van elkaar.
- NOT-002: payload is neutraal (`title`/`summary` + `reference: {type, id}`), nooit uitslag of inhoud.
  Bestaande meldingtypen nalopen.

## Acceptatie

- Twee keer GET laat het aantal ongelezen gelijk.
- Dismiss op een niet-dismissable melding geeft 422; read werkt wel.
