# Microsoft Graph Email Integration

Deze documentatie beschrijft de integratie van Microsoft Graph API voor het ophalen van e-mails binnen het CRM systeem.

## Overzicht

De Microsoft Graph integratie vervangt de IMAP-communicatie door een moderne API-gebaseerde benadering voor het ophalen van e-mails. Dit biedt betere betrouwbaarheid, schaalbaarheid en ondersteuning voor moderne Microsoft 365 omgevingen.

## Configuratie

### 1. Environment Variabelen

Voeg de volgende variabelen toe aan je `.env` bestand:

```env
# Microsoft Graph Configuration
GRAPH_CLIENT_ID=7657ba5b-xxxx-xxxx-xxxx-1259a387bbcf
GRAPH_TENANT_ID=dd126d7e-xxxx-xxxx-xxxx-548e33182661
GRAPH_CLIENT_SECRET=xxxx~6ymirceVRGyuzZ6zb3~xxxxxxx
GRAPH_MAILBOX=crm@privatescan.nl

# Switch naar Microsoft Graph driver
MAIL_RECEIVER_DRIVER=microsoft-graph
```

### 2. Azure App Registration

1. Ga naar de Azure Portal
2. Navigeer naar "Azure Active Directory" > "App registrations"
3. Maak een nieuwe app registratie aan
4. Configureer de volgende instellingen:
   - **Name**: CRM Email Sync
   - **Supported account types**: Single tenant
   - **Redirect URI**: Geen (niet nodig voor app-only flow)

5. Ga naar "Certificates & secrets" en maak een nieuwe client secret aan
6. Ga naar "API permissions" en voeg de volgende Microsoft Graph permissions toe:
   - `Mail.Read` (Application permission)
   - `Mail.ReadWrite` (Application permission)

7. Vergeet niet om "Grant admin consent" te klikken

### 3. Database Migratie

Voer de migratie uit om de email_logs tabel aan te maken:

```bash
php artisan migrate
```

## Functionaliteiten

### E-mail Synchronisatie

- **Frequentie**: Elke 5 minuten (configureerbaar in `app/Console/Kernel.php`)
- **Scope**: Alleen ongelezen berichten uit de Inbox
- **Limiet**: Maximaal 50 berichten per sync (ter voorkoming van timeouts)

### Automatische Entity Linking

Het systeem koppelt automatisch inkomende e-mails aan bestaande entiteiten:

1. **Person Matching**: Zoekt naar personen met het e-mailadres van de afzender
2. **Lead Matching**: Zoekt naar leads met het e-mailadres van de afzender
3. **Activity Creation**: Maakt automatisch een activiteit aan voor gekoppelde e-mails

### Attachment Ondersteuning

- Downloads en slaat attachments op via de bestaande attachment repository
- Ondersteunt alle Microsoft Graph ondersteunde bestandstypen

### Logging

Alle synchronisatie-activiteiten worden gelogd in de `email_logs` tabel:

- **sync_type**: 'graph'
- **started_at**: Starttijd van de sync
- **completed_at**: Eindtijd van de sync
- **processed_count**: Aantal succesvol verwerkte berichten
- **error_count**: Aantal fouten tijdens verwerking
- **error_message**: Foutmelding bij mislukte sync
- **metadata**: Extra informatie (mailbox, etc.)

## Commands

### Handmatige Sync

```bash
php artisan emails:sync-graph
```

### Scheduler

De sync draait automatisch elke 5 minuten via de Laravel scheduler. Zorg ervoor dat de scheduler actief is:

```bash
php artisan schedule:work
```

## Troubleshooting

### Veelvoorkomende Problemen

1. **Access Token Fout**
   - Controleer of de client credentials correct zijn
   - Verificeer dat de app registratie de juiste permissions heeft

2. **Mailbox Niet Gevonden**
   - Controleer of de mailbox bestaat in Microsoft 365
   - Verificeer dat de app registratie toegang heeft tot de mailbox

3. **Rate Limiting**
   - Microsoft Graph heeft rate limits
   - Het systeem respecteert automatisch deze limits

### Logs Bekijken

```bash
# Laravel logs
tail -f storage/logs/laravel.log

# Database logs
SELECT * FROM email_logs ORDER BY started_at DESC LIMIT 10;
```

## Technische Details

### Service Klasse

De `GraphMailService` klasse bevindt zich in `app/Services/Mail/GraphMailService.php` en implementeert:

- OAuth 2.0 client credentials flow
- Microsoft Graph API communicatie
- E-mail parsing en normalisatie
- Entity linking logica
- Attachment processing
- Error handling en logging

### Database Schema

```sql
-- Email logs tabel
CREATE TABLE email_logs (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    sync_type VARCHAR(50) NOT NULL DEFAULT 'graph',
    started_at TIMESTAMP NOT NULL,
    completed_at TIMESTAMP NULL,
    processed_count INT DEFAULT 0,
    error_count INT DEFAULT 0,
    error_message TEXT NULL,
    metadata JSON NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_sync_type_started (sync_type, started_at)
);
```

### API Endpoints

De integratie gebruikt de volgende Microsoft Graph endpoints:

- `POST /{tenant}/oauth2/v2.0/token` - Access token ophalen
- `GET /users/{mailbox}/mailFolders('Inbox')/messages` - Berichten ophalen
- `GET /users/{mailbox}/messages/{id}/attachments` - Attachments ophalen
- `PATCH /users/{mailbox}/messages/{id}` - Bericht als gelezen markeren

## Veiligheid

- Client credentials worden veilig opgeslagen in environment variabelen
- Access tokens worden niet permanent opgeslagen
- Alle API communicatie gebruikt HTTPS
- Geen gevoelige data wordt gelogd

## Monitoring

Het systeem biedt uitgebreide monitoring via:

- Database logging in `email_logs` tabel
- Laravel application logs
- Error tracking en reporting
- Performance metrics per sync

## Log Cleanup

### Automatische Cleanup

Het systeem voert automatisch dagelijks een cleanup uit van oude email logs:

```bash
# Handmatige cleanup
php artisan emails:cleanup-logs

# Cleanup met aangepaste retentie periode
php artisan emails:cleanup-logs --days=14
```

### Configuratie

De retentie periode kan worden geconfigureerd in de `.env`:

```env
# Standaard: 7 dagen
MAIL_LOG_RETENTION_DAYS=7
```

Of via de configuratie in `config/mail.php`:

```php
'log_retention_days' => env('MAIL_LOG_RETENTION_DAYS', 7),
```

### Scheduler

De cleanup draait automatisch dagelijks via de Laravel scheduler:

```php
// In app/Console/Kernel.php
$schedule->command('emails:cleanup-logs')->daily();
```

### Cleanup Features

- **Batch Processing**: Verwijderd logs in batches van 1000 om memory issues te voorkomen
- **Confirmation**: Vraagt bevestiging bij handmatige uitvoering
- **Logging**: Logt alle cleanup activiteiten
- **Configurable**: Aanpasbare retentie periode
- **Safe**: Alleen logs ouder dan de opgegeven periode worden verwijderd

## Ondersteuning

Voor vragen of problemen met de Microsoft Graph integratie, raadpleeg:

1. Deze documentatie
2. Microsoft Graph API documentatie
3. Laravel logs en database logs
4. Development team