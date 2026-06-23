# Privatescan CRM — Release 2026-06-23

---

## E-mail onderwerpregel

**Nieuwste updates Privatescan CRM — release 23 juni 2026**

---

## Intro

Beste gebruiker,

Hierbij de laatste verbeteringen en bugfixes van de Privatescan CRM. In deze release zijn met name het werken met activiteiten en het inkoopproces verder verbeterd. Hieronder vind je een overzicht van de belangrijkste wijzigingen.

---

## Wat is er nieuw?

- **Afgeronde activiteiten zijn nu alleen-lezen**
  Zodra een activiteit is afgemarkeerd als voltooid, kan de inhoud niet meer worden aangepast. Hiermee blijven afgeronde gegevens betrouwbaar en ongewijzigd.

- **Verbeterd activiteitenoverzicht**
  Activiteiten worden nu beter gegroepeerd en gesorteerd weergegeven, waardoor het overzicht overzichtelijker en sneller te lezen is.

- **Rapportage uploaden: meer mogelijkheden**
  Bij het uploaden van een rapportage kun je nu een persoon selecteren, meerdere bestanden tegelijk uploaden en de rapportage direct via het portaal publiceren.

- **Verbeterde stap 3 in het inkoopproces**
  De derde stap bij het verwerken van een inkoopfactuur is herzien voor een soepelere afhandeling en duidelijkere weergave.

- **Automatische terugbetaling bij verloren orderregel**
  Wanneer een orderregel op "verloren" wordt gezet, wordt de klant voortaan automatisch terugbetaald. Dit scheelt een handmatige stap en vermindert de kans op fouten.

- **Postcode is nu optioneel**
  Het invullen van een postcode bij een adres is niet langer verplicht, zodat ook adressen zonder postcode correct opgeslagen kunnen worden.

---

## Bugfixes

- Acties uitvoeren op een reeds afgeronde activiteit was in bepaalde gevallen nog mogelijk; dit is nu correct geblokkeerd.
- Fout- en bevestigingsmeldingen bij het uploaden van een rapportage zijn verbeterd.

---

## Aandachtspunten

- Er zijn **databasemigraties** toegevoegd. Voer na de deploy `php artisan migrate` uit.
- De wijziging rondom automatisch terugbetalen is nieuw gedrag — controleer bij twijfel de betalingsstatus van recent verloren orderregels.

---

## Afsluiting

We hopen dat deze verbeteringen het dagelijkse werk in de CRM soepeler maken. Heb je vragen of problemen na de update, neem dan contact op met het ontwikkelteam.

Met vriendelijke groet,
MB Software / Privatescan CRM-team

---

*Release tag: `release-2026-06-23` | Productiecommit: `6f5b0084a` | Branch: `main`*
