# 05 — Security-fixes bestaande patiënt-API

**Afhankelijk van:** –  ·  **Grootte:** S  ·  **Prio:** M  ·  **Bron:** api-voorstel K4, K6, K12, K17

## Scope

- **K12:** `PersonActivityController::store` logt de volledige request-body
  (`print_r($request->all())`, regel 85). Medische berichtinhoud in de log: regel weg.
- **K4:** `PUT patient/{id}/password` met bearer vraagt het huidige wachtwoord niet. `current_password`
  altijd verplicht (tot ticket 06 het wachtwoord helemaal naar Keycloak verplaatst).
- **K6:** `patient/{id}/password/reset` staat in de ingelogde groep. Verplaatsen naar
  `POST patient/password/reset` (API-key, met `reset_token`), naast `patient/forgot-password`.
- **K17:** rate limit `throttle:patient` op de hele `patient/*`-groep (uploads en berichten).

## Acceptatie

- Test: wachtwoord wijzigen zonder `current_password` geeft 422, ook met bearer.
- Test: reset werkt op de nieuwe route; de oude route geeft 404 of blijft tijdelijk als alias (besluiten).
- Test: na N requests per minuut 429.
- Geen `print_r` meer in `app/Http/Controllers/Api`.
