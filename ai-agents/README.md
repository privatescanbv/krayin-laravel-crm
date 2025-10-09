http://localhost:8501

## voorbeeld vragen
vanuit welke kanalen zijn de meeste leads binnen gekomen in de afgelopen 2 maanden
Toon mij de leads per maand in een grafiek
Toon mij de leads per maand

## risico's
Huidige ai-agent is als een scp server, direct op de database.
Daarmee kun je nu ook zeggen "Voeg een lead toe met de naam bart" of "Verwijder alle leads van voor 2023". Zonder validaties van API laag en/of rechten check.


## Development
docker compose build ai-agent && docker compose up ai-agent -d
docker compose build ai-frontend && docker compose up ai-frontend -d


## PDF

curl -X POST -F "file=@/Users/mark/Downloads/voorwaardenmb.pdf" http://localhost:8001/upload_pdf

curl -X POST "http://localhost:8001/ask_pdf?collection=voorwaardenmb" \
-H "Content-Type: application/json" \
-d '{"question": "Wat staat er in de algemene voorwaarden over aansprakelijkheid?"}'

