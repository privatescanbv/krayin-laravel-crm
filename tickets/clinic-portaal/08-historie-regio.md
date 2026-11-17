# 08 — "Historie": eerdere sale in een andere regio

**Afhankelijk van:** 02  ·  **Grootte:** S  ·  **Bron:** [gap-analyse](00-gap-analyse.md) §3.1 ("Historie: HWS" op de kaart)

## Scope

- Op kaart en detail: `history: [{id, label}]` met de regio's van **andere** Herniapoli-sales van dezelfde
  persoon(en), bijv. `[{id: "HWS", label: "HWS"}]`. Alleen de regio, geen inhoud van die andere sales.
- Berekend in één query voor de hele pagina (geen N+1).

## Acceptatie

- Patiënt met een eerdere HWS-sale en een huidige LWS-sale: kaart toont `history` met HWS.
- Geen andere gegevens van de eerdere sale in de response.
- Aantal queries blijft constant.
