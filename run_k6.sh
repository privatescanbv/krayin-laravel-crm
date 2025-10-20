#!/bin/bash
set -e


./vendor/bin/sail artisan optimize:clear

# 📦 1️⃣ Lees API key uit .env
if [ -f .env ]; then
  API_TOKEN=$(grep -E '^API_KEY_1=' .env | cut -d '=' -f2- | tr -d '"' | tr -d "'")
else
  echo "❌ .env bestand niet gevonden!"
  exit 1
fi

if [ -z "$API_TOKEN" ]; then
  echo "❌ API_KEY_1 niet gevonden in .env!"
  exit 1
fi

rm ./k6-scripts/report.html && rm ./k6-scripts/report.json || true

# 📊 2️⃣ Run de k6 performance test (rapport wordt automatisch gegenereerd)
echo "🚀 Running k6 load test..."
docker compose run --rm \
  -e API_TOKEN="$API_TOKEN" \
  k6 run /scripts/test.js

REPORT_PATH="$(pwd)/k6-scripts/report.html"

# 🌐 3️⃣ Open rapport in standaardbrowser
if [ -f "$REPORT_PATH" ]; then
  echo "✅ HTML report generated at: $REPORT_PATH"
  if command -v open &> /dev/null; then
    open "$REPORT_PATH"                # macOS
  elif command -v xdg-open &> /dev/null; then
    xdg-open "$REPORT_PATH" >/dev/null 2>&1 &  # Linux
  elif command -v wslview &> /dev/null; then
    wslview "$REPORT_PATH"             # Windows via WSL
  else
    echo "👉 Open handmatig in browser: $REPORT_PATH"
  fi
else
  echo "⚠️ Geen report.html gevonden — controleer of de test correct liep."
fi
