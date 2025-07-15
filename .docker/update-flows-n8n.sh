#!/bin/bash

FLOW_DIR="$1"
N8N_API_KEY="${N8N_API_KEY:?N8N_API_KEY is niet gezet}"
N8N_PROJECT_ID="${N8N_PROJECT_ID:?N8N_PROJECT_ID is niet gezet}"
N8N_API_URL="${N8N_API_URL:?N8N_API_URL is niet gezet}"

if ! command -v n8n &> /dev/null; then
  echo "❌ 'n8n' CLI niet beschikbaar."
  exit 1
fi

if [[ ! -d "$FLOW_DIR" ]]; then
  echo "❌ Map niet gevonden: $FLOW_DIR"
  exit 1
fi

echo "📂 Inhoud van map: $FLOW_DIR"
ls -l "$FLOW_DIR"

echo "📥 Workflows importeren..."
n8n import:workflow --separate --projectId="$N8N_PROJECT_ID" --input="$FLOW_DIR"

if [[ $? -eq 0 ]]; then
  echo "✅ Alle workflows succesvol geïmporteerd."
else
  echo "❌ Importeren mislukt."
fi
# ───── PATCH naar juiste project & active: true ─────
for file in "$FLOW_DIR"/*.json; do
  WORKFLOW_ID=$(jq -r '.id' "$file")

  if [[ "$WORKFLOW_ID" == "null" || -z "$WORKFLOW_ID" ]]; then
    echo "⚠️ Geen geldig ID gevonden in $file, overslaan..."
    continue
  fi

  echo "🚀 Workflow activeren: $WORKFLOW_ID"

  # Workflow activeren
  ACTIVATE_RESPONSE=$(curl -s -w "%{http_code}" -o /tmp/activate_response.json \
    -H "X-N8N-API-KEY: $N8N_API_KEY" \
    -X POST "$N8N_API_URL/$WORKFLOW_ID/activate")

  ACTIVATE_CODE="${ACTIVATE_RESPONSE:(-3)}"
  if [[ "$ACTIVATE_CODE" == "200" ]]; then
    echo "✅ Workflow $WORKFLOW_ID succesvol geactiveerd."
  else
    echo "❌ Fout bij activeren (HTTP $ACTIVATE_CODE):"
    cat /tmp/activate_response.json
  fi

done
