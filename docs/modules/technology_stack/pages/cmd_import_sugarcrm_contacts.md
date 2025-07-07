### Import Sugar contacts into Persons

Dit commando maakt het mogelijk om snel persons aan te maken via de API voor het vullen van de database met test data.

**Basis Gebruik**

```bash
# Maak 10 persons aan (standaard)
sail artisan import:persons-from-sugarcrm

# Import 100 persons
sail artisan import:persons-from-sugarcrm --limit=100

# Import alle persons
sail artisan import:persons-from-sugarcrm --limit=10000

```

**Parameters**

**Verplichte Parameters**
- `count` - Aantal persons om aan te maken (standaard: 10)

**Optionele Parameters**
- `--dry-run` - Toon wat er zou worden aangemaakt zonder daadwerkelijk aan te maken
- `--limit` - Max aantal contacts om te importeren 

**Voorbeelden**

**Basis Data Population**
```bash
# Maak 50 persons aan
php artisan import:persons-from-sugarcrm --limit=50
```

**Dry Run (Test zonder aanmaken)**
```bash
# Toon wat er zou worden aangemaakt zonder daadwerkelijk aan te maken
php artisan import:persons-from-sugarcrm 10 --dry-run
```

**Output**


**Tips voor Data Population**

1. **Start klein** - Begin met 10-50 persons om te testen
2. **Gebruik dry-run** - Test eerst met `--dry-run` om te zien wat er wordt aangemaakt

**Troubleshooting**

**Database Errors**
- Controleer of er voldoende database ruimte beschikbaar is
- Monitor database performance tijdens het aanmaken
- Controleer of alle benodigde tabellen en relaties bestaan
