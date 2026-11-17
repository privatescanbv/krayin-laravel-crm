# 17 — Betaalstatus per sale

**Afhankelijk van:** 02  ·  **Grootte:** S  ·  **Prio:** M  ·  **Bron:** FIN-002, FIN-005

## Scope

- `payment_status: {id, label}` op de sale-resource (ticket 02): `open`, `partially_paid`, `paid`,
  `refunded`, afgeleid uit `OrderPayment` tegen het orderbedrag van de orders in de sale.
- Alleen de status en het openstaande bedrag; geen inkoopprijzen of interne bedragen (FIN-005).

## Open

- Patiëntfacturen en offertes (FIN-001/004) hebben nog geen bron (V-P5): niet in dit ticket.

## Acceptatie

- Tests voor de vier statussen; sale zonder order geeft `null`.
