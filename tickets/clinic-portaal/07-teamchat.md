# 07 — Teamchat per sale (basis)

- **Status:** gebouwd, nog niet gecommit/gedeployed, geen handmatige browsercheck van de admin-tab (2026-11-24). Repo krayin-laravel-crm, branch `development`, werkboom (geen commit).
  - Gedaan: `ActivityType::TEAM_CHAT` (`team_chat`, geen migratie, `is_done=1`, geen `person_id`); clinic `GET/POST …/sales-leads/{saleId}/messages` (aflopend, gepagineerd, 404 buiten scope, max 2000 tekens); `counts.chat` op kaart en detail via `withCount('teamChatMessages')`; Bearer-whitelist in `ApiKeyAuth`; CRM-admin tab "Teamchat" op de sale (Blade-form + redirect, `admin.sales-leads.team-chat.store`).
  - Gescheiden van patiëntchat: eigen type, dus `PatientMessage`-observers en patiënt-endpoints raken het niet. Uitgesloten via `Activity::scopeWithoutTeamChat` in admin- en service-activiteitenlijst, `ActivityDataGrid` en Vue-filtertypes.
  - Afwijking: kaart-`counts` is nieuw (`{chat}`), detail-`counts` kreeg `chat` naast `files`. Admin-chat ververst niet live (pagina-reload).
  - Tests: `ClinicSalesLeadsTest` (uitgebreid), `SalesLeadTeamChatTest`; 157 groen.
  - Portaal (repo clinic-portal, branch `development`, niet gecommit): tab "Teamchat" op de patiëntkaart naar het ontwerp in `design/` (banner "nooit voor de patiënt", oudste bericht bovenaan, formulier "Bericht aan het zorgteam" / "Bericht plaatsen"); routes `sales.messages` en `sales.messages.store`, `ClinicCrmClient::fetchSaleMessages/addSaleMessage`; 108 portaaltests groen.
  - Nog: Scribe-docs regenereren, handmatig in de browser testen (portaal en admin), `counts.chat` op de kaart in het bord tonen (portaal gebruikt het nog niet), ongelezen-stipje uit het ontwerp bewust niet (gelezen-status buiten scope).

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
