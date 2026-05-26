<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI Prompts per Use Case
    |--------------------------------------------------------------------------
    |
    | Static system prompts keyed by use case identifier. Consumed by LlmService.
    |
    */

    'email_sender_extraction' => <<<'PROMPT'
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

];
