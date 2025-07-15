#!/bin/bash

FLOW_DIR="$1"
N8N_API_KEY="${N8N_API_KEY:?N8N_API_KEY is niet gezet}"
N8N_API_URL="${N8N_API_URL:?N8N_API_URL is niet gezet}"

if ! command -v n8n &> /dev/null; then
  echo "❌ 'n8n' CLI niet beschikbaar."
  exit 1
fi

if [[ ! -d "$FLOW_DIR" ]]; then
  echo "❌ Map niet gevonden: $FLOW_DIR"
  exit 1
fi

# ───── Project ophalen of aanmaken ─────
get_projects() {
  curl -s -H "X-N8N-API-KEY: $N8N_API_KEY" "$N8N_API_URL/projects"
}

create_project() {
  curl -s -H "X-N8N-API-KEY: $N8N_API_KEY" -H "Content-Type: application/json" \
    -X POST "$N8N_API_URL/projects" \
    -d '{"name": "AutoImportedProject"}'
}

if [[ -n "$N8N_PROJECT_ID" ]]; then
  echo "🔍 Controleren of project $N8N_PROJECT_ID bestaat..."
  PROJECT_EXISTS=$(curl -s -H "X-N8N-API-KEY: $N8N_API_KEY" "$N8N_API_URL/projects/$N8N_PROJECT_ID" | jq -r '.id // empty')

  if [[ -z "$PROJECT_EXISTS" ]]; then
    echo "➕ Project bestaat niet, wordt aangemaakt..."
    N8N_PROJECT_ID=$(create_project | jq -r '.id')
    echo "✅ Nieuw project ID: $N8N_PROJECT_ID"
  else
    echo "✅ Project bestaat al: $N8N_PROJECT_ID"
  fi
else
  echo "📋 Geen project ID opgegeven, ophalen alle projecten..."
  PROJECTS=$(get_projects)
  PROJECT_COUNT=$(echo "$PROJECTS" | jq 'length')

  if [[ "$PROJECT_COUNT" -eq 1 ]]; then
    N8N_PROJECT_ID=$(echo "$PROJECTS" | jq -r '.[0].id')
    echo "✅ Enig project gevonden: $N8N_PROJECT_ID"
  elif [[ "$PROJECT_COUNT" -gt 1 ]]; then
    echo "❌ Meerdere projecten gevonden. Specificeer een N8N_PROJECT_ID in je .env."
    echo "$PROJECTS" | jq '.[] | {id, name}'
    exit 1
  else
    echo "➕ Geen projecten gevonden, nieuw project wordt aangemaakt..."
    N8N_PROJECT_ID=$(create_project | jq -r '.id')
    echo "✅ Nieuw project ID: $N8N_PROJECT_ID"
  fi
fi

# ───── Workflows importeren ─────
echo "📂 Inhoud van map: $FLOW_DIR"
ls -l "$FLOW_DIR"

echo "📥 Workflows importeren in project $N8N_PROJECT_ID..."
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
