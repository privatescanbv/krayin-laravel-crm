# 04 — Auth: `patient/me/*` en Person in de middleware

**Trello:** https://trello.com/c/VXYr9V4D
**Trello-label:** `NOT_READY`

**Afhankelijk van:** –  ·  **Grootte:** M  ·  **Prio:** M  ·  **Bron:** api-voorstel K1, K2, K3

Architecture Mode: raakt auth en publieke contracten. Eerst bespreken, dan bouwen.

## Doel

Een bearer-caller hoeft zijn Keycloak-id niet meer in de URL te zetten, en controllers krijgen een `Person`
in plaats van zelf te resolven.

## Scope

- Nieuwe middleware (bijv. `patient.person`) die de `Person` op de request zet:
  - bearer → `sub` → `Person` via `KeycloakService::resolvePersonOrUser()`;
  - API-key → `{id}` uit het pad (alleen voor service-to-service).
  - CRM-user zonder person: één gedrag voor alle routes (advies: 403). Nu wisselt het per controller
    (lege lijst, 404, 200 met log).
- Routegroep `patient/me/*` naast `patient/{id}/*`, met dezelfde controllers. De oude routes blijven tot het
  oude portaal weg is.
- Bestaande `Patient*`-controllers: `resolvePersonOrUser()` eruit, `Person` uit de request.
- K3 (API-key mag elk dossier): afhankelijk van V-P1. Óf scopes per API-key (`patient:read` / `patient:write`),
  óf `patient/me/*` alleen voor bearer.

## Acceptatie

- Elke bestaande patiënt-test draait ook tegen `patient/me/*` met een bearer (gemockte Socialite, patroon
  `PatientDocumentsTest`).
- Bearer van patiënt A kan nooit bij data van B, ook niet via `{id}`.
- CRM-user zonder person krijgt overal hetzelfde antwoord.
