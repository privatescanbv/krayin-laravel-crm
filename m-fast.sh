#!/bin/bash

# === CONFIG ===
URL="https://newcrm.dev.privatescan.nl/llm-fast/v1/chat/completions"

# === INPUT uit tekstbestand *(pdf)===
TEXT=$(cat tekst.txt)

# === JSON request maken (veilig escapen) ===
jq -n \
  --arg text "$TEXT" \
  '{
    temperature: 0.1,
    messages: [
      {
        role: "user",
        content:
"Analyseer de volgende medische tekst (afkomstig uit een PDF) en maak een korte medische samenvatting voor een arts.

Regels:
- Laat naam, adres, geboortedatum en andere persoonsgegevens weg.
- Gebruik zakelijke medische taal.
- Geef alleen relevante medische inhoud weer.
- Als informatie ontbreekt: noteer Niet vermeld.
- Voeg geen uitleg toe buiten de samenvatting.

Structuur:
- Lopend verhaal van maximaal 15 zinnen.
- Verwerk expliciet de volgende onderdelen:
  - Duur van de klachten
  - Medische beschrijving van de klachten (inclusief eventuele uitstraling, bv dorsolateraal)
  - Tintelingen / doof gevoel
  - Krachtverlies
  - VAS pijnscore
  - Verergering bij lopen / zitten / staan / liggen
  - Uitgevoerde conservatieve therapieën

Afsluiting:
- Korte conclusie in 3-5 zinnen voor de arts.

Tekst:
" + $text
      }
    ]
  }' > requestf.json

# === API call ===
curl "$URL" \
  -H "Content-Type: application/json" \
  -d @requestf.json
