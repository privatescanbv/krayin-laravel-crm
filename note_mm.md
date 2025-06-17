# Notes

* Attributen aanpassen, kan via settings. Daarmee niet uit te rollen, ik stel voor om hier verplicht db migratie scripts voor te gaan gebruiken. (type -> kanaal van een lead aangepast)
TODO
* CRM workflow: set afdeling als er een nieuwe lead binnen komt, afhankelijk van de waarde van Kanaal.
* Nieuwe lead auto doorzetten naar klant data bijvullen en een taak aanmaken op linda om dit uit te voeren.
* Indien Linda de taak op done heeft gezet -> lead status op klant adviseren zetten met een taak om de lead te bellen, op Linda.
* CRM kent activity van een lead, maar niet echt een taak. (geen deadline, taak bord per medewerker)
* Taak/activiteit zou ik vanuit n8n op een groep willen zetten, als Hernia. Kan nu niet. Iemand uit een groep dient de taak op te claimen en dus dient zichtbaar te zijn op zijn/haar bord.
* AUTH API key implementeren in CRM (zie privateForms). Credentials in n8n key vault plaatsen.
* Contact person
  * ontbreken velden, ontbreken van validatie, ontbreken van dubbele personen check, familie als organiatie?


notes:
- lead aanmaken, contact persoon is verplicht. Willen wij niet.
- Contact zou je meerdere leads willen kunnen koppelen, maar dat is niet mogelijk.
- Pas logo aan in configuratie
- Voeg taal NL toe
