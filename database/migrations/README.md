# Database Migraties

Deze folder bevat 80+ migratie bestanden die de volledige database structuur definiëren.

## Performance Optimalisatie

Voor snellere `migrate:fresh` uitvoering, gebruik **schema dumps**:

```bash
# 1. Voer alle migraties uit
php artisan migrate:fresh --seed

# 2. Maak een schema dump (10x sneller daarna!)
php artisan schema:dump

# 3. Test: nu is migrate:fresh veel sneller
php artisan migrate:fresh --seed
```

📖 **Zie volledige documentatie**: `/docs/database-schema-dumps.md`

## Migratie Overzicht

### Laravel Core (2019-2024)
- Failed jobs, personal access tokens, job batches, jobs

### Business Logic (2025)
- **Q1**: Workflows, activities, leads integraties
- **Q2**: Addresses, lead channels, departments, anamnesis, lead persons
- **Q3**: Call statuses, clinics, resources, shifts, products, partner products

### Package Wijzigingen
Migraties die package tabellen wijzigen (users, leads, persons, activities, etc.) blijven behouden voor compatibiliteit.

## Wanneer Nieuwe Migratie Maken?

✅ **Maak nieuwe migratie voor:**
- Nieuwe tabellen
- Nieuwe kolommen
- Index wijzigingen
- Foreign key wijzigingen

❌ **Niet aanpassen:**
- Bestaande migraties NOOIT aanpassen
- Dit breekt voor collega's die al gemigreerd hebben

## Best Practices

### 1. Naamgeving
```bash
# Goed: beschrijvend en duidelijk
2025_10_02_120000_add_status_to_orders_table.php
2025_10_02_130000_create_invoices_table.php

# Slecht: vaag
2025_10_02_120000_update_stuff.php
```

### 2. Atomair
Elke migratie moet:
- Één logische wijziging maken
- Rollback-baar zijn (down() methode)
- Idempotent zijn (meerdere keren uitvoeren = zelfde resultaat)

### 3. Testing
Test elke nieuwe migratie:
```bash
php artisan migrate              # Test up()
php artisan migrate:rollback     # Test down()
php artisan migrate              # Test nogmaals up()
```

### 4. Dependencies
Als migraties afhankelijk zijn:
- Gebruik juiste volgorde in timestamps
- Check in down() of afhankelijkheden bestaan

## Troubleshooting

### "Table already exists"
```bash
# Rollback en opnieuw
php artisan migrate:rollback
php artisan migrate
```

### "Foreign key constraint fails"
- Check volgorde van migraties
- Zorg dat parent table eerst bestaat
- Gebruik `->nullable()` of `->constrained()` correct

### "Class not found"
```bash
# Regenerate autoload
composer dump-autoload
```

## Schema Dump Workflow

### Zonder Dump (huidig)
```
migrate:fresh
├─ 2019_08_19... (0.2s)
├─ 2019_12_14... (0.2s)  
├─ 2024_03_21... (0.2s)
├─ ... 77 more ...
└─ 2025_10_01... (0.2s)
Total: ~20 seconden
```

### Met Dump (aanbevolen)
```
migrate:fresh
├─ database/schema/mysql-schema.sql (1s) ⚡
└─ [alleen nieuwe migraties na dump]
Total: ~2 seconden
```

## Commands

```bash
# Normale migratie
php artisan migrate

# Fresh install (alle data weg!)
php artisan migrate:fresh

# Fresh met seeders
php artisan migrate:fresh --seed

# Rollback laatste batch
php artisan migrate:rollback

# Rollback alles
php artisan migrate:reset

# Status van migraties
php artisan migrate:status

# Schema dump maken (performance boost!)
php artisan schema:dump

# Schema dump + oude migraties weggooien
php artisan schema:dump --prune
```

## Tips

1. **Development**: Gebruik `migrate:fresh` vaak om consistentie te waarborgen
2. **Production**: Gebruik alleen `migrate` (nooit fresh!)
3. **Testing**: Migraties worden automatisch uitgevoerd in tests
4. **CI/CD**: Schema dumps werken automatisch in pipelines

## Vragen?

Zie `/docs/database-schema-dumps.md` voor uitgebreide uitleg over performance optimalisatie.
