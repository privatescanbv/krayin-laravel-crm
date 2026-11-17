# 07 — Teamchat per sale (basis)

**Afhankelijk van:** –  ·  **Grootte:** M  ·  **Bron:** [gap-analyse](00-gap-analyse.md) §3.2, v1 G-7

## Doel

Arts en team praten per sale met elkaar (tab Chat, "Letzte Nachrichten", aantal op de kaart). Dit staat los van
de interne notities en nooit gekoppeld aan de patiëntchat (`PatientMessage`).

## Scope (basis)

- Opslag: `Activity` op de sale met een eigen soort voor chatberichten (advies v1: hergebruik Activity, geen
  nieuw berichtensysteem), zodat notities (`type=note`) en chat gescheiden blijven.
- `GET clinic/{keycloakUserId}/sales-leads/{saleId}/messages`: aflopend, gepagineerd; auteur (naam), tijd, tekst.
- `POST …/messages`: tekst (platte tekst, max lengte), op naam van de arts.
- `counts.chat` op kaart (`sales-leads`) en detail, zonder N+1 (`withCount`).
- CRM-admin: chat zichtbaar en te beantwoorden op de sale, zodat de zorgadviseur kan reageren.
- Bearer-whitelist in `ApiKeyAuth` uitbreiden.

Niet in dit ticket: threads, @mentions, bijlagen, verwijderen, gelezen-status, meldingen, aparte
gespreksverloop per regio ("eigener Gesprächsverlauf"). Zie open vragen.

## Acceptatie

- Bericht van de arts verschijnt in de CRM-admin en andersom.
- Chatberichten komen niet in de notitielijst en notities niet in de chat.
- Sale van een andere arts: 404 voor lezen en plaatsen.
- `counts.chat` klopt op kaart en detail; aantal queries constant.
