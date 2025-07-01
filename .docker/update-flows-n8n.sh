#!/bin/bash

# ───── CONFIG ────────────────────────────────────────────────

#N8N_API_KEY="awRfSc%3Q*fgMm!UJL9F"
N8N_API_KEY="eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiJjYTQ1YzNhYi0yOTE2LTQyMmEtODMzOS02M2E3MzNmYTU2NjEiLCJpc3MiOiJuOG4iLCJhdWQiOiJwdWJsaWMtYXBpIiwiaWF0IjoxNzUxMzEwMTg4fQ.5LqHoAMDcaYEEzVWi1DGPkS2LvwALeuK_J_uNFE3ABc"
N8N_API_URL="http://n8n:5678/api/v1/workflows"
FLOW_DIR="$1"

# ───── CHECKS ────────────────────────────────────────────────

if ! command -v jq &> /dev/null; then
  echo "❌ 'jq' is vereist maar niet geïnstalleerd."
  exit 1
fi

if [[ ! -d "$FLOW_DIR" ]]; then
  echo "❌ Map niet gevonden: $FLOW_DIR"
  exit 1
fi

# ───── VERWERKEN ─────────────────────────────────────────────
# Loop door alle .json-bestanden
for file in "$FLOW_DIR"/*.json; do
  [[ ! -f "$file" ]] && echo "⚠️  Geen JSON-bestanden gevonden in $FLOW_DIR" && continue

  echo "🔄 Verwerken: $file"

  WORKFLOW_ID=$(jq -r '.id' "$file")

  if [[ -z "$WORKFLOW_ID" || "$WORKFLOW_ID" == "null" ]]; then
    echo "❌ Geen geldig 'id' veld gevonden in $file"
    continue
  fi

  echo "📎 Workflow ID: $WORKFLOW_ID"

  # Bestaat deze workflow?
  HTTP_EXISTS=$(curl -s -o /dev/null -w "%{http_code}" \
    -H "X-N8N-API-KEY: $N8N_API_KEY" \
    "$N8N_API_URL/$WORKFLOW_ID")

  if [[ "$HTTP_EXISTS" == "200" ]]; then
    echo "🔁 Workflow bestaat, uitvoeren: PUT"

    RESPONSE=$(curl -s -w "%{http_code}" -o /tmp/n8n_response.json \
      -H "X-N8N-API-KEY: $N8N_API_KEY" \
      -H "Content-Type: application/json" \
      -X PUT "$N8N_API_URL/$WORKFLOW_ID" \
      --data-binary "@$file")

  else
    echo "➕ Workflow bestaat niet, uitvoeren: POST (als nieuwe)"

    TMP_NO_ID="/tmp/$(basename "$file").noid.json"
    jq 'del(.id)' "$file" > "$TMP_NO_ID"

    CLEANED_JSON=$(jq 'del(.id, .createdAt, .updatedAt, .versionId)' "$file")

    RESPONSE=$(curl -s -w "%{http_code}" -o /tmp/n8n_response.json \
      -H "X-N8N-API-KEY: $N8N_API_KEY" \
      -H "Content-Type: application/json" \
      -X POST "$N8N_API_URL" \
      --data-binary "$CLEANED_JSON")

    rm "$TMP_NO_ID"
  fi

  HTTP_CODE="${RESPONSE:(-3)}"
  if [[ "$HTTP_CODE" == "200" || "$HTTP_CODE" == "201" ]]; then
    echo "✅ Workflow succesvol verwerkt ($HTTP_CODE)."
  else
    echo "❌ Fout (HTTP $HTTP_CODE):"
    cat /tmp/n8n_response.json
  fi

done
