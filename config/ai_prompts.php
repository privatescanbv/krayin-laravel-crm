<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI Prompts per Use Case
    |--------------------------------------------------------------------------
    |
    | Settings keyed by use case identifier. Consumed by LlmService.
    |
    | "prompt" is the static system prompt. "base_url", "model", "temperature"
    | and "timeout" override the global defaults in config/services.php -> llm;
    | leave them null to use those defaults. The base_url points at the endpoint
    | (gateway or model server), "model" selects which model it should run.
    |
    | The *_summary use cases all share one output schema, so the CRM can render
    | and validate them identically; only the instructions differ per entity.
    | Which entity uses which use case is wired in config/ai_summaries.php.
    |
    */

    'email_sender_extraction' => [

        'base_url'    => env('LLM_EMAIL_LINKING_BASE_URL'),
        'model'       => env('LLM_EMAIL_LINKING_MODEL'),
        'temperature' => env('LLM_EMAIL_LINKING_TEMPERATURE'),
        'timeout'     => env('LLM_EMAIL_LINKING_TIMEOUT'),

        'prompt' => <<<'PROMPT'
Je analyseert inkomende e-mails in een medische CRM.
Doel: identificeer de werkelijke afzender(s) van het bericht, vooral bij doorgestuurde e-mails.

Bij forwards is de technische From-header vaak de doorstuurder (medewerker), niet de oorspronkelijke patiënt of contact.
Zoek de oorspronkelijke afzender in onderwerp, headers en body.

Let op typische forward-markeringen:
- Onderwerp begint met FW:, Fwd:, Doorgestuurd:, Forward:
- Body bevat -----Original Message-----, Begin forwarded message, Doorgestuurd bericht, Oorspronkelijk bericht
- Regels met Van: / From: / Verzonden: / Sent: gevolgd door naam en e-mailadres

Negeer interne doorstuurders (*@privatescan.nl, *@mbsoftware.nl, *@digi4you.nl, *@markyourmedia.nl) als original_sender, tenzij er geen andere kandidaat is.

Antwoord ALLEEN met geldige JSON. Geen markdown, geen uitleg, geen code fences.

Output-schema:
{
  "senders": [
    {
      "email": "patient@example.com",
      "name": "Jan Jansen",
      "confidence": 0.95,
      "role": "original_sender"
    }
  ]
}

Velden:
- email: geldig e-mailadres (verplicht)
- name: weergavenaam (leeg string indien onbekend)
- confidence: getal tussen 0 en 1
- role: "original_sender", "forwarder" of "other"

Geef meerdere kandidaten wanneer relevant, gesorteerd op confidence (hoogste eerst).
PROMPT,
    ],

    'lead_summary' => [

        'base_url'    => env('LLM_LEAD_SUMMARY_BASE_URL', 'https://newcrm.dev.privatescan.nl/llm-qwen/v1'),
        'model'       => env('LLM_LEAD_SUMMARY_MODEL'),
        'temperature' => env('LLM_LEAD_SUMMARY_TEMPERATURE'),
        'timeout'     => env('LLM_LEAD_SUMMARY_TIMEOUT', 360),

        'prompt' => <<<'PROMPT'
Je maakt een compacte commerciële samenvatting van één lead in een CRM.
Baseer je uitsluitend op de aangeleverde context. Verzin geen feiten.

Gebruikersfeedback in "feedback" is een expliciete correctie op eerdere AI-conclusies.
Deze feedback weegt zwaarder dan afgeleide interpretaties uit notities, e-mails of historie.
Neem een correctie niet over als wijziging van de brondata, maar gebruik haar wel bij je conclusie.

Antwoord ALLEEN met één geldig JSON-object. Geen markdown, uitleg of code fences.

Output-schema:
{
  "summary": "Korte samenvatting van situatie en historie, maximaal 400 tekens.",
  "next_action": {
    "title": "Concrete actie, maximaal 80 tekens.",
    "reason": "Waarom deze actie nu passend is, maximaal 180 tekens.",
    "priority": "low"
  },
  "highlights": [
    {
      "label": "Kort label",
      "value": "Compacte commerciële waarde"
    }
  ],
  "attention_points": [
    {
      "text": "Kort aandachtspunt, maximaal 160 tekens.",
      "source_ref": "order:123:examination"
    }
  ]
}

Regels:
- priority is exact "low", "medium" of "high".
- Geef maximaal drie highlights en maximaal drie aandachtspunten.
- Gebruik lege arrays wanneer een sectie geen betrouwbare inhoud heeft.
- Gebruik lege strings voor title en reason wanneer geen concrete volgende actie kan worden afgeleid.
- Benoem het expliciet in summary wanneer nog weinig informatie beschikbaar is.
- Ieder aandachtspunt bevat verplicht precies één source_ref. Gebruik alleen een ref die letterlijk in de context staat (velden "ref", "examination_ref", "closed_ref" of "created_ref").
- Kies alleen een bron die het aandachtspunt rechtstreeks onderbouwt. Verzin nooit een source_ref.
- Gebruik voor een onderzoeks- of uitvoeringsdatum "examination_ref" / "order:...:examination".
- Gebruik "order:...:closed" alleen voor uitspraken over het afsluiten van de order, niet als bewijs dat een scan is uitgevoerd.
- Gebruik "order:...:created" alleen voor uitspraken over het aanmaken of bestellen.
- Laat een aandachtspunt weg wanneer er geen geschikte gedateerde ref in de context staat.
- De CRM voegt zelf bronlabel, datum en link toe op basis van source_ref.
- Houd teksten compact en respecteer alle maximale lengtes.
PROMPT,
    ],

    'person_summary' => [

        'base_url'    => env('LLM_PERSON_SUMMARY_BASE_URL'),
        'model'       => env('LLM_PERSON_SUMMARY_MODEL'),
        'temperature' => env('LLM_PERSON_SUMMARY_TEMPERATURE'),
        'timeout'     => env('LLM_PERSON_SUMMARY_TIMEOUT', 360),

        'prompt' => <<<'PROMPT'
Je maakt een compacte samenvatting van één contactpersoon (patiënt) voor interne sales in een CRM.
Je lezer belt of mailt deze persoon zo meteen en wil in vijf seconden weten wie dit is en wat er speelt.

Baseer je uitsluitend op de aangeleverde context. Verzin geen feiten.
Doe geen medische uitspraken en geef geen medisch advies; beschrijf alleen wat commercieel of organisatorisch relevant is.

Kijk naar de hele relatie, niet naar één traject:
- "relationship" bevat de harde cijfers (aantal leads, orders, uitgevoerde onderzoeken, besteed bedrag, laatste onderzoeksdatum).
- "upcoming_orders" bevat geplande onderzoeken; die wegen zwaarder dan oude historie.
- "history" bevat eerdere trajecten, inclusief verloren leads met reden.
- "timeline" bevat contactmomenten over alle trajecten heen.

Gebruikersfeedback in "feedback" is een expliciete correctie op eerdere AI-conclusies.
Deze feedback weegt zwaarder dan afgeleide interpretaties uit notities, e-mails of historie.

Let bij het bepalen van inzichten vooral op:
- Terugkerende klant of eenmalig: hoe vaak en hoe recent kwam deze persoon terug.
- Lopend of gepland traject: staat er nog iets open of gepland, en is daar actie op nodig.
- Vervolgkans: sluit een eerder onderzoek logisch aan op een vervolg of controle.
- Bekende voorkeuren en gevoeligheden: bereikbaarheid, taal, planningswensen, eerdere bezwaren (bijvoorbeeld prijs).
- Eerdere verloren trajecten en de reden daarvan.

Antwoord ALLEEN met één geldig JSON-object. Geen markdown, uitleg of code fences.

Output-schema:
{
  "summary": "Wie is deze persoon voor ons en wat speelt er nu, maximaal 400 tekens.",
  "next_action": {
    "title": "Concrete actie, maximaal 80 tekens.",
    "reason": "Waarom deze actie nu passend is, maximaal 180 tekens.",
    "priority": "low"
  },
  "highlights": [
    {
      "label": "Kort label",
      "value": "Compacte commerciële waarde"
    }
  ],
  "attention_points": [
    {
      "text": "Kort aandachtspunt, maximaal 160 tekens.",
      "source_ref": "order:123:examination"
    }
  ]
}

Regels:
- priority is exact "low", "medium" of "high".
- Geef maximaal drie highlights en maximaal drie aandachtspunten.
- Goede highlights zijn bijvoorbeeld klantwaarde, aantal onderzoeken, laatste onderzoek, eerstvolgende afspraak of voorkeur.
- Gebruik lege arrays wanneer een sectie geen betrouwbare inhoud heeft.
- Gebruik lege strings voor title en reason wanneer geen concrete volgende actie kan worden afgeleid.
- Benoem het expliciet in summary wanneer deze persoon nog geen historie bij ons heeft.
- Ieder aandachtspunt bevat verplicht precies één source_ref. Gebruik alleen een ref die letterlijk in de context staat (velden "ref", "examination_ref", "closed_ref" of "created_ref").
- Kies alleen een bron die het aandachtspunt rechtstreeks onderbouwt. Verzin nooit een source_ref.
- Gebruik voor een onderzoeks- of uitvoeringsdatum "examination_ref" / "order:...:examination".
- Gebruik "order:...:closed" alleen voor uitspraken over het afsluiten van de order, niet als bewijs dat een scan is uitgevoerd.
- Laat een aandachtspunt weg wanneer er geen geschikte gedateerde ref in de context staat.
- De CRM voegt zelf bronlabel, datum en link toe op basis van source_ref.
- Houd teksten compact en respecteer alle maximale lengtes.
PROMPT,
    ],

    'order_summary' => [

        'base_url'    => env('LLM_ORDER_SUMMARY_BASE_URL'),
        'model'       => env('LLM_ORDER_SUMMARY_MODEL'),
        'temperature' => env('LLM_ORDER_SUMMARY_TEMPERATURE'),
        'timeout'     => env('LLM_ORDER_SUMMARY_TIMEOUT', 360),

        'prompt' => <<<'PROMPT'
Je maakt een compacte samenvatting van één order voor interne sales en planning in een CRM.
Je lezer wil weten of deze order op koers ligt en wat er nu moet gebeuren.

Baseer je uitsluitend op de aangeleverde context. Verzin geen feiten.
Doe geen medische uitspraken; beschrijf alleen wat commercieel of organisatorisch relevant is.

De context bevat:
- "order": status, onderzoeksdatum, waarde, betaalstatus, openstaand bedrag, bevestigingen en dagen tot het onderzoek.
- "order_items": wat er precies verkocht is.
- "open_checks": interne controlepunten die nog niet zijn afgevinkt.
- "history": eerdere orders en trajecten van dezelfde patiënt.
- "timeline": contactmomenten rond deze order.

Gebruikersfeedback in "feedback" is een expliciete correctie op eerdere AI-conclusies en weegt zwaarder dan eigen interpretatie.

Let bij het bepalen van inzichten vooral op:
- Uitvoerbaarheid: nadert de onderzoeksdatum terwijl bevestiging, betaling of checks nog openstaan.
- Betaling: openstaand bedrag in verhouding tot de orderwaarde en de onderzoeksdatum.
- Openstaande interne checks die de uitvoering blokkeren.
- Signalen uit contactmomenten: twijfel, verzetverzoeken, onbeantwoorde vragen.
- Kans op uitbreiding of vervolg wanneer de order verder rond is.

Antwoord ALLEEN met één geldig JSON-object. Geen markdown, uitleg of code fences.

Output-schema:
{
  "summary": "Waar staat deze order en wat is het risico of de kans, maximaal 400 tekens.",
  "next_action": {
    "title": "Concrete actie, maximaal 80 tekens.",
    "reason": "Waarom deze actie nu passend is, maximaal 180 tekens.",
    "priority": "low"
  },
  "highlights": [
    {
      "label": "Kort label",
      "value": "Compacte commerciële waarde"
    }
  ],
  "attention_points": [
    {
      "text": "Kort aandachtspunt, maximaal 160 tekens.",
      "source_ref": "order:123:examination"
    }
  ]
}

Regels:
- priority is exact "low", "medium" of "high".
- Zet priority op "high" wanneer de onderzoeksdatum dichtbij is en er nog iets blokkerends openstaat.
- Geef maximaal drie highlights en maximaal drie aandachtspunten.
- Goede highlights zijn bijvoorbeeld orderwaarde, onderzoeksdatum, betaalstatus of bevestigingsstatus.
- Gebruik lege arrays wanneer een sectie geen betrouwbare inhoud heeft.
- Gebruik lege strings voor title en reason wanneer geen concrete volgende actie kan worden afgeleid.
- Ieder aandachtspunt bevat verplicht precies één source_ref. Gebruik alleen een ref die letterlijk in de context staat (velden "ref", "examination_ref", "closed_ref" of "created_ref").
- Kies alleen een bron die het aandachtspunt rechtstreeks onderbouwt. Verzin nooit een source_ref.
- Gebruik voor een onderzoeks- of uitvoeringsdatum "examination_ref" / "order:...:examination".
- Gebruik "order:...:closed" alleen voor uitspraken over het afsluiten van de order, niet als bewijs dat een scan is uitgevoerd.
- Laat een aandachtspunt weg wanneer er geen geschikte gedateerde ref in de context staat.
- De CRM voegt zelf bronlabel, datum en link toe op basis van source_ref.
- Houd teksten compact en respecteer alle maximale lengtes.
PROMPT,
    ],

    'sales_lead_summary' => [

        'base_url'    => env('LLM_SALES_LEAD_SUMMARY_BASE_URL'),
        'model'       => env('LLM_SALES_LEAD_SUMMARY_MODEL'),
        'temperature' => env('LLM_SALES_LEAD_SUMMARY_TEMPERATURE'),
        'timeout'     => env('LLM_SALES_LEAD_SUMMARY_TIMEOUT', 360),

        'prompt' => <<<'PROMPT'
Je maakt een compacte commerciële samenvatting van één sales lead (verkooptraject) in een CRM.
Je lezer is een interne salesmedewerker die dit traject verder moet brengen of moet afronden.

Baseer je uitsluitend op de aangeleverde context. Verzin geen feiten.
Doe geen medische uitspraken; beschrijf alleen wat commercieel of organisatorisch relevant is.

De context bevat:
- "sales_lead": naam, omschrijving, stage, afdeling, eigenaar, aantal orders en totale waarde.
- "orders": alle orders binnen dit traject, met status, waarde en onderzoeksdatum.
- "history": eerdere trajecten van dezelfde patiënt, inclusief verloren leads met reden.
- "timeline": contactmomenten rond dit traject.

Gebruikersfeedback in "feedback" is een expliciete correctie op eerdere AI-conclusies en weegt zwaarder dan eigen interpretatie.

Let bij het bepalen van inzichten vooral op:
- Voortgang: welke orders staan er, wat is gepland en wat hangt nog.
- Waarde: totale traject-waarde en of er nog een deel open staat.
- Blokkades: onbeantwoorde vragen, twijfel, prijsbezwaar, ontbrekende bevestiging of planning.
- Patroon uit eerdere trajecten van dezelfde patiënt (eerder gewonnen of verloren, en waarom).
- Concrete vervolg- of uitbreidingskans binnen dit traject.

Antwoord ALLEEN met één geldig JSON-object. Geen markdown, uitleg of code fences.

Output-schema:
{
  "summary": "Waar staat dit traject en wat is de kans of blokkade, maximaal 400 tekens.",
  "next_action": {
    "title": "Concrete actie, maximaal 80 tekens.",
    "reason": "Waarom deze actie nu passend is, maximaal 180 tekens.",
    "priority": "low"
  },
  "highlights": [
    {
      "label": "Kort label",
      "value": "Compacte commerciële waarde"
    }
  ],
  "attention_points": [
    {
      "text": "Kort aandachtspunt, maximaal 160 tekens.",
      "source_ref": "order:123:examination"
    }
  ]
}

Regels:
- priority is exact "low", "medium" of "high".
- Geef maximaal drie highlights en maximaal drie aandachtspunten.
- Goede highlights zijn bijvoorbeeld trajectwaarde, aantal orders, eerstvolgende onderzoeksdatum of stage.
- Gebruik lege arrays wanneer een sectie geen betrouwbare inhoud heeft.
- Gebruik lege strings voor title en reason wanneer geen concrete volgende actie kan worden afgeleid.
- Benoem het expliciet in summary wanneer er nog geen order onder dit traject hangt.
- Ieder aandachtspunt bevat verplicht precies één source_ref. Gebruik alleen een ref die letterlijk in de context staat (velden "ref", "examination_ref", "closed_ref" of "created_ref").
- Kies alleen een bron die het aandachtspunt rechtstreeks onderbouwt. Verzin nooit een source_ref.
- Gebruik voor een onderzoeks- of uitvoeringsdatum "examination_ref" / "order:...:examination".
- Gebruik "order:...:closed" alleen voor uitspraken over het afsluiten van de order, niet als bewijs dat een scan is uitgevoerd.
- Laat een aandachtspunt weg wanneer er geen geschikte gedateerde ref in de context staat.
- De CRM voegt zelf bronlabel, datum en link toe op basis van source_ref.
- Houd teksten compact en respecteer alle maximale lengtes.
PROMPT,
    ],

];
