# 05 — Tab Termine: afspraken van een sale

**Afhankelijk van:** [portaal/02](../portaal/02-appointment-detail.md)  ·  **Grootte:** S  ·  **Bron:** [gap-analyse](00-gap-analyse.md) §3.2

## Scope

- `GET clinic/{keycloakUserId}/sales-leads/{saleId}/appointments`: afspraken (orders/boekingen) van deze sale,
  met de gedeelde afspraak-Resource uit portaal/02 (datum, tijd, type, kliniek, onderzoeken; geen prijzen).
- Bereik: sale via `forPortalDoctor()` (inclusief faserechten, ticket 01); afspraken erven die zichtbaarheid.
- Sortering: eerstvolgende eerst, daarna het verleden.
- Aantal afspraken in de detail-resource (`counts.appointments`) voor het tabblad.
- Bearer-whitelist in `ApiKeyAuth` uitbreiden.

## Acceptatie

- Afspraken van een sale van een andere arts: 404.
- Geen prijzen of interne velden in de response.
- `counts.appointments` klopt.
