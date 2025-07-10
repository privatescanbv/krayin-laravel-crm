### Lead Data Population Commando

Dit commando maakt het mogelijk om snel leads aan te maken via de API voor het vullen van de database met test data.

**Basis Gebruik**

```bash
# Maak 10 leads aan (standaard)
sail artisan leads:create

# Maak 100 leads aan
sail artisan leads:create 100

# Maak 1000 leads aan
sail artisan leads:create 1000
```

**Parameters**

**Verplichte Parameters**
- `count` - Aantal leads om aan te maken (standaard: 10)

**Optionele Parameters**
- `--department` - Department (Hernia of Privatescan)
- `--source` - Lead source ID
- `--type` - Lead type ID  
- `--user` - User ID
- `--delay` - Vertraging tussen requests in milliseconden (standaard: 100ms)
- `--dry-run` - Toon wat er zou worden aangemaakt zonder daadwerkelijk aan te maken

**Voorbeelden**

**Basis Data Population**
```bash
# Maak 50 leads aan
sail artisan leads:create 50
```

**Test met Department**
```bash
# Maak 100 Hernia leads aan
sail artisan leads:create 100 --department=hernia

# Maak 100 Privatescan leads aan
sail artisan leads:create 100 --department=privatescan
```

**Rate Limiting**
```bash
# Maak 100 leads aan met 200ms vertraging (langzamer)
sail artisan leads:create 100 --delay=200

# Maak 100 leads aan met 50ms vertraging (sneller)
sail artisan leads:create 100 --delay=50
```

**Dry Run (Test zonder aanmaken)**
```bash
# Toon wat er zou worden aangemaakt zonder daadwerkelijk aan te maken
sail artisan leads:create 10 --dry-run
```

**Volledige Configuratie**
```bash
# Maak 200 leads aan met alle opties
sail artisan leads:create 200 \
    --department=hernia \
    --source=2 \
    --type=1 \
    --user=1 \
    --delay=150
```

**Output**

Het commando toont:
- Progress bar tijdens het aanmaken
- Vertraging tussen requests (indien ingesteld)
- Totaal aantal succesvol aangemaakte leads
- Aantal errors (indien aanwezig)
- Details van errors (indien aanwezig)

**Voorbeelden van Output**

```
Creating 100 leads...
Delay between requests: 100ms
████████████████████████████████████████ 100/100

Completed!
Successfully created: 100 leads
```

**Tips voor Data Population**

1. **Start klein** - Begin met 10-50 leads om te testen
2. **Gebruik dry-run** - Test eerst met `--dry-run` om te zien wat er wordt aangemaakt
3. **Pas delay aan** - Verhoog de delay als je "Too Many Requests" krijgt
4. **Test verschillende scenarios** - Test met verschillende departments en configuraties
5. **Monitor de database** - Houd database performance in de gaten bij grote aantallen

**Troubleshooting**

**"Too Many Requests" Error**
- Verhoog de `--delay` parameter (bijv. `--delay=200` of `--delay=500`)
- De standaard delay is 100ms, probeer 200ms of meer
- Voor grote aantallen, gebruik een hogere delay

**Veel Errors**
- Controleer of de API endpoint bereikbaar is
- Controleer of de opgegeven IDs (source, type, user) bestaan
- Controleer de API logs voor meer details

**Timeout Errors**
- De timeout is ingesteld op 30 seconden per request
- Voor grote aantallen, voer het commando meerdere keren uit met kleinere batches

**Database Errors**
- Controleer of er voldoende database ruimte beschikbaar is
- Monitor database performance tijdens het aanmaken
- Controleer of alle benodigde tabellen en relaties bestaan
