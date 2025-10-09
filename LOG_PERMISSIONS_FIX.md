# Log Permissions Fix - Robuuste Oplossing

## Probleem
De applicatie op productie gaf 500 errors bij het toevoegen van een kliniek en andere acties. Het probleem was dat log files verschillende eigenaren hadden (sommige `www-data`, andere `root`), wat permission conflicts veroorzaakte.

## Oplossing
Een robuuste oplossing is geïmplementeerd met de volgende componenten:

### 1. Verbeterde Start Script (`.docker/start.sh`)
- **Permission Fix Function**: Automatisch herstellen van log permissions
- **Log Rotation**: Automatische rotatie van log files om grote bestanden te voorkomen
- **Proactive Permission Setting**: Permissions worden ingesteld voor en na Laravel commands

### 2. Log Rotation (`/etc/logrotate.d/laravel`)
- **Daily Rotation**: Log files worden dagelijks geroteerd
- **7 Day Retention**: Oude logs worden 7 dagen bewaard
- **Compression**: Oude logs worden gecomprimeerd
- **Correct Permissions**: Nieuwe log files krijgen automatisch de juiste permissions

### 3. Background Monitoring (Supervisor)
- **Log Permission Fixer**: Controleert elke 30 seconden op verkeerde permissions en herstelt deze
- **Log Monitor**: Rapporteert elke 5 minuten over log status en permissions

### 4. Health Check (`.docker/health-check.sh`)
- **Permission Verification**: Controleert of log directory schrijfbaar is
- **Ownership Check**: Verificeert dat alle log files eigendom zijn van `www-data`
- **Laravel Health**: Controleert of Laravel applicatie reageert

### 5. Verbeterde Cron Jobs
- **www-data User**: Cron jobs draaien als `www-data` waar mogelijk
- **Proper Log Paths**: Logs worden geschreven naar de juiste locatie
- **Log Rotation Cron**: Automatische log rotation via cron

## Gebruik

### Health Check
```bash
# In de container
/health-check.sh
```

### Log Monitoring
```bash
# In de container
/usr/share/nginx/html/.docker/monitor-logs.sh
```

### Manual Permission Fix
```bash
# In de container
find /usr/share/nginx/html/storage/logs -name "*.log" -exec chown www-data:www-data {} \;
chmod -R 775 /usr/share/nginx/html/storage
```

## Voordelen
1. **Automatisch Herstel**: Permissions worden automatisch hersteld
2. **Proactieve Monitoring**: Problemen worden vroegtijdig gedetecteerd
3. **Log Rotation**: Voorkomt grote log files die problemen kunnen veroorzaken
4. **Health Checks**: Docker health checks detecteren permission problemen
5. **Robuust**: Meerdere lagen van bescherming tegen permission issues

## Monitoring
- Log permission fixer draait elke 30 seconden
- Log monitor rapporteert elke 5 minuten
- Health check draait elke 30 seconden via Docker
- Log rotation draait dagelijks om 2:00

## Troubleshooting
Als er nog steeds permission problemen zijn:

1. Check de health check output
2. Bekijk de log monitor output
3. Controleer supervisor logs
4. Voer manual permission fix uit
5. Herstart de container als laatste redmiddel