#!/bin/bash
# use this script to generate the documentation locally (for development purposes)
/bin/bash ./doc.sh

# url's aanpassen naar niet admin links.
HTML_FILE="docs/html/index.html"

# Controleer of het bestand bestaat
if [ ! -f "$HTML_FILE" ]; then
  echo "Bestand '$HTML_FILE' niet gevonden."
  exit 1
fi

# Vervang alle /admin/docs/resources/ naar ./resources/
sed -i.bak 's|/admin/docs/resources/|./resources/|g' "$HTML_FILE"
