# Anamnesis Missing Records Fix

## Probleem
De anamnesis sectie is niet zichtbaar in de lead view omdat bestaande leads geen anamnesis record hebben. De anamnesis functionaliteit werd later toegevoegd en maakt alleen anamnesis records aan voor nieuwe leads.

## Oorzaak
In `packages/Webkul/Lead/src/Repositories/LeadRepository.php` wordt de anamnesis alleen aangemaakt in de `create` method (regels 191-212), niet voor bestaande leads.

## Oplossing
Er is een Artisan command gemaakt om anamnesis records aan te maken voor bestaande leads die er nog geen hebben.

### Command Details
- **Bestand**: `app/Console/Commands/CreateMissingAnamnesis.php`
- **Signature**: `anamnesis:create-missing`
- **Functie**: Zoekt alle leads zonder anamnesis record en maakt er een aan

### Uitvoeren van het Command

```bash
# In Docker omgeving
docker exec -it krayin-crm-1 php artisan anamnesis:create-missing

# Of direct als PHP beschikbaar is
php artisan anamnesis:create-missing
```

### Wat het Command Doet
1. Zoekt alle leads die geen anamnesis record hebben
2. Maakt voor elke lead een anamnesis record aan met:
   - UUID als primary key
   - Koppeling naar de lead
   - Standaard naam: "Anamnesis voor [Lead Title]"
   - Correcte user_id en timestamps
3. Toont progress en eventuele errors

### Verwachte Output
```
Starting to create missing anamnesis records...
Found 5 leads without anamnesis records.
✓ Created anamnesis for lead: Test Lead 1 (ID: 1)
✓ Created anamnesis for lead: Test Lead 2 (ID: 2)
✓ Created anamnesis for lead: Test Lead 3 (ID: 3)
✓ Created anamnesis for lead: Test Lead 4 (ID: 4)
✓ Created anamnesis for lead: Test Lead 5 (ID: 5)

Completed!
Created: 5 anamnesis records
```

### Na het Uitvoeren
Na het uitvoeren van dit command zouden alle leads een anamnesis sectie moeten hebben in de lead view, met een "Bewerken" knop die naar het anamnesis edit formulier leidt.

### Verificatie
Je kunt controleren of het gewerkt heeft door:
1. Naar een lead view te gaan (`/admin/leads/view/[ID]`)
2. Te kijken of de "Anamnesis" sectie zichtbaar is
3. Te klikken op "Bewerken" om het anamnesis formulier te openen

### Technische Details
- Het command gebruikt `whereDoesntHave('anamnesis')` om leads zonder anamnesis te vinden
- Gebruikt de `Lead` model relatie die al geconfigureerd is
- Maakt gebruik van dezelfde logic als bij nieuwe leads
- Heeft error handling voor edge cases