# 03 — Diagnose en Behandlungsberatung wijzigen vanuit het portaal

**Afhankelijk van:** 02  ·  **Grootte:** S  ·  **Bron:** [gap-analyse](00-gap-analyse.md) §3.2 (knoppen "Bearbeiten")

## Scope

- `PATCH clinic/{keycloakUserId}/sales-leads/{saleId}` met alleen `diagnosis` en `treatment_advice`
  (whitelist; andere velden negeren of 422).
- Bereik: `forPortalDoctor()`; alleen sales in een fase waar de rol rechten op heeft (ticket 01).
- Conflictcontrole: request bevat het `updated_at` dat de arts zag; wijkt dat af, dan `409` met de actuele
  waarden. Zo overschrijft de arts geen wijziging van de zorgadviseur.
- Wijziging op naam van de arts (`User`) in het Wijzigingslogboek.
- Validatie in een Form Request (max lengte, platte tekst).
- Route toevoegen aan de bearer-whitelist in `ApiKeyAuth` (`isClinicSaleRoute`).
- Response: de detail-resource.

## Acceptatie

- Wijzigen van eigen sale lukt en staat in het logboek met de arts als auteur.
- Sale van een andere arts of in een fase zonder rechten geeft 404.
- Verouderde `updated_at` geeft 409; niets opgeslagen.
- Andere velden (bijv. `pipeline_stage_id`, `region`) worden niet gewijzigd via dit endpoint.
