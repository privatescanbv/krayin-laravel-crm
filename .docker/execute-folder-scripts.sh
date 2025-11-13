#!/bin/bash

# Stel de directory in waarvan de scripts uitgevoerd moeten worden
SCRIPT_DIR="./bin/post-deploy/user-entry-scripts"

# create symlink for artisan, so we can keep our custom scripts to a different path (shock env)
ln -s /usr/share/nginx/html/artisan /var/www/html/artisan || true

# Controleer of de directory bestaat
if [ ! -d "$SCRIPT_DIR" ]; then
  echo "Directory $SCRIPT_DIR bestaat niet."
  exit 1
fi

# Zoek en voer alle .sh-bestanden uit, gesorteerd op naam
for script in $(find "$SCRIPT_DIR" -type f -name "*.sh" | sort); do
  echo "Voer uit: $script"
  chmod +x "$script" # Zorg ervoor dat het script uitvoerbaar is
  "$script" || echo "Fout bij uitvoeren van $script, doorgaan..."
done
