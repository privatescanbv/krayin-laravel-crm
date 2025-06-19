# SugarCRM Import Command

Deze command importeert Person entiteiten uit een SugarCRM database naar Krayin CRM.

## Configuratie

### 1. Database Connectie

Voeg de volgende variabelen toe aan je `.env` bestand:

```env
# SugarCRM Database Connectie
SUGARCRM_DB_HOST=127.0.0.1
SUGARCRM_DB_PORT=3306
SUGARCRM_DB_DATABASE=sugarcrm
SUGARCRM_DB_USERNAME=your_username
SUGARCRM_DB_PASSWORD=your_password
SUGARCRM_DB_PREFIX=
```

### 2. SugarCRM Tabel Structuur

De command verwacht de volgende kolommen in de SugarCRM `contacts` tabel:

- `id` - Unieke ID
- `first_name` - Voornaam
- `last_name` - Achternaam
- `email1` - Primaire email
- `email2` - Secundaire email (optioneel)
- `phone_work` - Werk telefoon
- `phone_mobile` - Mobiele telefoon (optioneel)
- `date_entered` - Aanmaakdatum
- `date_modified` - Wijzigingsdatum

## Gebruik

### Basis Command

```bash
php artisan import:persons-from-sugarcrm
```

### Opties

- `--connection=sugarcrm` - Database connectie naam (standaard: sugarcrm)
- `--table=contacts` - Bron tabel naam (standaard: contacts)
- `--limit=100` - Aantal records om te importeren (standaard: 100)
- `--dry-run` - Toon wat er geïmporteerd zou worden zonder daadwerkelijk te importeren

### Voorbeelden

#### Dry Run (test zonder importeren)
```bash
php artisan import:persons-from-sugarcrm --dry-run
```

#### Importeer 500 records
```bash
php artisan import:persons-from-sugarcrm --limit=500
```

#### Importeer uit andere tabel
```bash
php artisan import:persons-from-sugarcrm --table=leads
```

#### Importeer uit andere database connectie
```bash
php artisan import:persons-from-sugarcrm --connection=old_sugarcrm
```

## Functionaliteiten

### Duplicaat Detectie
- Personen met hetzelfde email adres worden overgeslagen
- Voorkomt dubbele imports

### Meerdere Contact Methoden
- Primaire email wordt opgeslagen in `email` veld
- Secundaire email wordt opgeslagen als extra email record
- Werk telefoon wordt opgeslagen in `phone` veld
- Mobiele telefoon wordt opgeslagen als extra telefoon record

### Foutafhandeling
- Elke record wordt individueel verwerkt
- Fouten worden gelogd maar stoppen de import niet
- Progress bar toont voortgang
- Samenvatting van resultaten

### Logging
- Alle fouten worden gelogd in `storage/logs/laravel.log`
- Inclusief record ID en foutmelding

## Output

De command toont:
- Database connectie status
- Aantal gevonden records
- Progress bar tijdens import
- Samenvatting: geïmporteerd, overgeslagen, fouten

## Troubleshooting

### Database Connectie Fout
- Controleer de database credentials in `.env`
- Test de connectie handmatig
- Controleer of de database server bereikbaar is

### Tabel Niet Gevonden
- Controleer of de tabel naam correct is
- Controleer of de tabel bestaat in de SugarCRM database

### Kolommen Ontbreken
- Pas de command aan voor je specifieke SugarCRM schema
- Voeg ontbrekende kolommen toe aan de mapping

## Aanpassen voor Andere Schema's

Als je SugarCRM een ander schema heeft, pas dan de kolom mapping aan in de `importRecords` methode:

```php
$person = Person::create([
    'first_name' => $record->your_first_name_column ?? '',
    'last_name' => $record->your_last_name_column ?? '',
    'email' => $record->your_email_column ?? '',
    'phone' => $record->your_phone_column ?? '',
    // ... andere velden
]);
``` 