# 06 — Wachtwoord alleen in Keycloak

**Trello:** https://trello.com/c/U9cv1QGn
**Trello-label:** `NOT_READY`

**Afhankelijk van:** 05  ·  **Grootte:** M  ·  **Prio:** M  ·  **Bron:** api-voorstel K5, V-P2

Security-ticket. Architecture Mode: raakt Person, User en Keycloak-sync.

## Doel

Het CRM slaat geen (terug te ontsleutelen) patiëntwachtwoord meer op.

## Scope

- `PatientPasswordController` zet het wachtwoord alleen via de Keycloak admin-API; niet meer op `Person` of
  `User`.
- `getDecryptedPassword()` en de opgeslagen versleutelde wachtwoorden uitfaseren. Eerst alle gebruikers
  van deze waarde vinden (grep) en vervangen.
- Migratie die de kolom(men) leegmaakt, pas nadat niets er meer van leest.

## Acceptatie

- Test: wachtwoord wijzigen roept de Keycloak-client aan (fake) en schrijft niets naar de database.
- Geen code leest nog een patiëntwachtwoord terug.
