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
        'timeout'     => env('LLM_LEAD_SUMMARY_TIMEOUT'),

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

];
