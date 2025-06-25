# Notes

* Attributen aanpassen, kan via settings. Daarmee niet uit te rollen, ik stel voor om hier verplicht db migratie scripts voor te gaan gebruiken. (type -> kanaal van een lead aangepast)
TODO
* CRM workflow: set afdeling als er een nieuwe lead binnen komt, afhankelijk van de waarde van Kanaal.
* Nieuwe lead auto doorzetten naar klant data bijvullen en een taak aanmaken op linda om dit uit te voeren.
* Indien Linda de taak op done heeft gezet -> lead status op klant adviseren zetten met een taak om de lead te bellen, op Linda.
* CRM kent activity van een lead, maar niet echt een taak. (geen deadline, taak bord per medewerker)
* Taak/activiteit zou ik vanuit n8n op een groep willen zetten, als Hernia. Kan nu niet. Iemand uit een groep dient de taak op te claimen en dus dient zichtbaar te zijn op zijn/haar bord. (-> Group aan activity kunnen koppelen)
* AUTH API key implementeren in CRM (zie privateForms). Credentials in n8n key vault plaatsen.
* Contact person
  * ontbreken velden, ontbreken van validatie, ontbreken van dubbele personen check, familie als organiatie?
* taal nl niet aanwezig

notes:
- Contact zou je meerdere leads willen kunnen koppelen, maar dat is niet mogelijk.
- Pas logo aan in configuratie
- Voeg taal NL toe

vragen:
- Bij een lead kun je producten koppelen. Willen we dit behouden? Voordeel als iemand anders de producten gaat inplannen b.v. Verwacht het niet voor private scan? dezelfde persoon. Indien ja -> producten bij leads eruit slopen.

gemaakte keuzes:
- CRM workflow gebruiken om velden te zetten op basis van andere velden (zie afdeling). De rest in n8n. Nadeel: soms complexer, je zit met volgorde etc in n8n.
- pipeline leads: Ik stel voor om er een voor hernia en private scan te maken, ondanks dat ze dezelfde statussen hebben.


windows

chmod -R 775 storage bootstrap/cache
docker compose up
