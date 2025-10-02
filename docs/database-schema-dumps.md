# Database Schema Dumps - Snelle Migraties

## Probleem
Met 80+ migratie bestanden duurt `php artisan migrate:fresh` lang omdat elke migratie individueel wordt uitgevoerd.

## Oplossing
Laravel's schema dump functionaliteit laadt een SQL dump van je complete schema in één keer, en voert alleen daarna nieuwe migraties uit.

## Hoe het werkt

### 1. Eerste keer: Schema Dump maken

Na een succesvolle `migrate:fresh`:

```bash
php artisan schema:dump
```

Dit commando:
- Maakt een SQL bestand: `database/schema/mysql-schema.sql` (of sqlite/pgsql)
- Bevat de COMPLETE database structuur
- Nieuwe migraties blijven gewoon werken

### 2. Optioneel: Oude migraties verwijderen (prune)

Als je de oude migrati bestanden wilt opschonen:

```bash
php artisan schema:dump --prune
```

⚠️ **LET OP**: Dit verwijdert alle migratie bestanden die in de dump zitten!
- Doe dit ALLEEN als je zeker weet dat de dump correct is
- Houd eerst een backup
- Test eerst zonder --prune

### 3. Vanaf nu: Snelle migraties

Bij volgende `migrate:fresh`:
```bash
php artisan migrate:fresh --seed
```

Laravel:
1. Laadt eerst de SQL dump (< 1 seconde) 
2. Voert alleen nieuwe migraties uit (sinds de dump)
3. Resultaat: ~10x sneller!

## Workflow

### Reguliere ontwikkeling
```bash
# Normale migraties blijven gewoon werken
php artisan migrate

# Fresh install is nu snel
php artisan migrate:fresh
```

### Schema dump updaten
Wanneer je meerdere nieuwe migraties hebt toegevoegd:

```bash
# 1. Fresh migrate
php artisan migrate:fresh

# 2. Verifieer dat alles werkt
php artisan db:seed
# Test je applicatie

# 3. Maak nieuwe dump
php artisan schema:dump

# 4. Optioneel: verwijder oude migraties
php artisan schema:dump --prune
```

## Best Practices

### ✅ DO
- Maak regelmatig nieuwe dumps (elke 10-20 migraties)
- Commit de dump naar git
- Test de dump op een lege database voor je --prune doet
- Houd package migraties altijd (niet prunen)

### ❌ DON'T  
- Niet --prune gebruiken als je niet zeker bent
- Niet de dump handmatig bewerken
- Niet oude dumps bewaren (git history is genoeg)

## CI/CD Integratie

In je CI pipeline:
```bash
# Schema dump exists? Gebruik die!
php artisan migrate:fresh --seed
```

Laravel detecteert automatisch de dump en gebruikt die.

## Troubleshooting

### "Schema dump not found"
Normale situatie - Laravel voert gewoon alle migraties uit.

### Dump is outdated
```bash
rm database/schema/*.sql
php artisan schema:dump
```

### Tests falen na dump
Zorg dat test database ook de dump gebruikt:
```php
// phpunit.xml of .env.testing
DB_CONNECTION=mysql
```

## Technische Details

### Wat zit er in de dump?
- Alle `CREATE TABLE` statements
- Alle indices en foreign keys
- Geen data (tenzij je seeders gebruikt)
- Migrations tabel entries (welke migraties zijn uitgevoerd)

### Database-specifiek
Laravel maakt automatisch de juiste dump voor je database:
- MySQL: `database/schema/mysql-schema.sql`
- PostgreSQL: `database/schema/pgsql-schema.sql`  
- SQLite: `database/schema/sqlite-schema.sql`

### Performance
- **Zonder dump**: 80 migraties = ~15-30 seconden
- **Met dump**: 1 SQL file = ~1-2 seconden
- **Verbetering**: ~10-15x sneller

## Voorbeeld Workflow

```bash
# Stap 1: Fresh migrate met alle 80 migraties (langzaam)
php artisan migrate:fresh --seed
# ⏱️  ~20 seconden

# Stap 2: Maak dump
php artisan schema:dump
# ✅ database/schema/mysql-schema.sql aangemaakt

# Stap 3: Test de dump
php artisan migrate:fresh --seed  
# ⏱️  ~2 seconden (10x sneller!)

# Stap 4 (optioneel): Ruim op
php artisan schema:dump --prune
# 🗑️  Oude migraties verwijderd (nu in dump)

# Stap 5: Commit
git add database/schema/mysql-schema.sql
git commit -m "Add database schema dump for faster migrations"
```

## Wanneer nieuwe dump maken?

Maak een nieuwe dump wanneer:
- Je 10+ nieuwe migraties hebt toegevoegd
- Je een grote refactoring hebt gedaan
- Je performance problemen ziet
- Voor een nieuwe release

## Vragen?

**Q: Wat gebeurt er met bestaande migraties?**  
A: Die blijven gewoon werken. De dump is alleen een "shortcut" voor de initiële setup.

**Q: Moet ik migraties deleten na --prune?**  
A: Laravel doet dat automatisch. Je kunt ze veilig uit git verwijderen.

**Q: Werkt dit met package migraties?**  
A: Ja! Alle migraties (ook uit packages) komen in de dump.

**Q: Wat als een teamlid geen dump heeft?**  
A: Laravel voert automatisch alle migraties uit. Geen probleem!
