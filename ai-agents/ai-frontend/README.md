http://localhost:8501

## voorbeeld vragen
Toon mij de leads per maand in een grafiek
Toon mij de leads per maand

## risico's
Huidige ai-agent is als een scp server, direct op de database.
Daarmee kun je nu ook zeggen "Voeg een lead toe met de naam bart" of "Verwijder alle leads van voor 2023". Zonder validaties van API laag en/of rechten check.


## Development
docker compose build ai-agent && docker compose up ai-agent -d
docker compose build ai-frontend && docker compose up ai-frontend -d
